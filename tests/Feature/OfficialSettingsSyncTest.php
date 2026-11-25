<?php

use App\Filament\Pages\SystemSettingsPage;
use App\Models\SuratTugas;
use App\Models\User;
use App\Services\OfficialSettingsSyncService;
use App\Services\SppdService;
use App\Settings\SystemSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    SystemSettings::fake(SystemSettings::defaults());
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Organik', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
    Gate::before(fn () => true);
});

it('resolves user profile details for kepala, ppk, and bendahara while keeping legacy strings in sync', function () {
    $kepala = User::factory()->create(['name' => 'Khomarudin']);
    $kepala->profile()->updateOrCreate(
        ['user_id' => $kepala->id],
        [
            'full_name' => 'Khomarudin, S.ST',
            'nip' => '197512091999011001',
            'jabatan' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
        ]
    );

    $ppk = User::factory()->create(['name' => 'Sulaeman']);
    $ppk->profile()->updateOrCreate(
        ['user_id' => $ppk->id],
        [
            'full_name' => 'Sulaeman, S.Stat',
            'nip' => '198204012006041015',
            'jabatan' => 'Statistisi Ahli Muda',
        ]
    );

    $bendahara = User::factory()->create(['name' => 'Khusnul Khotimah']);
    $bendahara->profile()->updateOrCreate(
        ['user_id' => $bendahara->id],
        [
            'full_name' => 'Khusnul Khotimah, S.Stat',
            'nip' => '198610252009022009',
            'jabatan' => 'Pranata Keuangan APBN',
        ]
    );

    $kasubag = User::factory()->create(['name' => 'Ahmad Kasubag']);
    $kasubag->profile()->updateOrCreate(
        ['user_id' => $kasubag->id],
        [
            'full_name' => 'Ahmad Fauzi, S.Tr.Stat',
            'nip' => '199001012012011002',
            'jabatan' => 'Kepala Subbagian Umum',
        ]
    );

    $payload = app(OfficialSettingsSyncService::class)->buildSyncedAttributes([
        'kepala_user_id' => $kepala->id,
        'ppk_user_id' => $ppk->id,
        'bendahara_user_id' => $bendahara->id,
        'kasubag_umum_user_id' => $kasubag->id,
    ]);

    expect($payload['cert_signer_name'])->toBe('Khomarudin, S.ST')
        ->and($payload['cert_signer_nip'])->toBe('197512091999011001')
        ->and($payload['cert_signer_title'])->toBe('Kepala Badan Pusat Statistik Kabupaten Demak')
        ->and($payload['ppk_name'])->toBe('Sulaeman, S.Stat')
        ->and($payload['ppk_nip'])->toBe('198204012006041015')
        ->and($payload['ppk_title'])->toBe('Pejabat Pembuat Komitmen')
        ->and($payload['bendahara_name'])->toBe('Khusnul Khotimah, S.Stat')
        ->and($payload['bendahara_nip'])->toBe('198610252009022009')
        ->and($payload['bendahara_title'])->toBe('Bendahara Pengeluaran')
        ->and($payload['kasubag_umum_name'])->toBe('Ahmad Fauzi, S.Tr.Stat')
        ->and($payload['kasubag_umum_nip'])->toBe('199001012012011002')
        ->and($payload['kasubag_umum_title'])->toBe('Kepala Subbagian Umum');
});

it('excludes mitra users from official employee dropdown options', function () {
    $organik = User::factory()->create(['name' => 'Pegawai Organik']);
    $organik->syncRoles(['Organik']);
    $organik->profile()->update(['nip' => '199001012015011001']);

    $mitra = User::factory()->create(['name' => 'Mitra Lapangan']);
    $mitra->syncRoles(['Mitra']);

    $options = OfficialSettingsSyncService::getEmployeeOptions();

    expect($options)->toHaveKey($organik->id)
        ->and($options)->not->toHaveKey($mitra->id)
        ->and($options[$organik->id])->toContain('199001012015011001');
});

it('feeds synced settings seamlessly into SppdService and SuratTugas without breaking legacy string consumers', function () {
    $kepala = User::factory()->create(['name' => 'Khomarudin']);
    $kepala->profile()->updateOrCreate(
        ['user_id' => $kepala->id],
        ['full_name' => 'Khomarudin, S.ST', 'nip' => '197512091999011001']
    );

    $ppk = User::factory()->create(['name' => 'Sulaeman']);
    $ppk->profile()->updateOrCreate(
        ['user_id' => $ppk->id],
        ['full_name' => 'Sulaeman, S.Stat', 'nip' => '198204012006041015']
    );

    $settings = app(SystemSettings::class);
    $synced = app(OfficialSettingsSyncService::class)->buildSyncedAttributes([
        'kepala_user_id' => $kepala->id,
        'cert_signer_title' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
        'ppk_user_id' => $ppk->id,
        'ppk_title' => 'Pejabat Pembuat Komitmen',
    ]);
    $settings->fill($synced)->save();

    $pegawai = User::factory()->create(['name' => 'Budi']);
    $st = SuratTugas::create([
        'user_id' => $pegawai->id,
        'nomor_surat' => 'B-0001/33210/KP.650/2026',
        'nomor_urut' => 1,
        'kode_klasifikasi' => 'KP.650',
        'jabatan' => 'Staff',
        'keperluan' => 'Uji SPPD',
        'tempat_tugas' => 'Demak',
        'tanggal' => now(),
        'waktu_mulai' => now(),
        'waktu_selesai' => now(),
        'signer_name' => $settings->cert_signer_name,
        'signer_nip' => $settings->cert_signer_nip,
        'signer_title' => $settings->cert_signer_title,
        'signer_city' => $settings->cert_city,
    ]);

    $sppd = app(SppdService::class)->createSppd($st, [
        'nomor_sppd' => 'B-0001/33210/SE2026/KP.650/2026',
        'nomor_urut_sppd' => 1,
    ]);

    expect($st->signer_name)->toBe('Khomarudin, S.ST')
        ->and($st->signer_nip)->toBe('197512091999011001')
        ->and($sppd->ppk_name)->toBe('Sulaeman, S.Stat')
        ->and($sppd->ppk_nip)->toBe('198204012006041015');
});

it('renders and saves SystemSettingsPage with official user dropdowns via Livewire 4', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create(['name' => 'Admin']);
    $admin->syncRoles(['super_admin']);

    $kepala = User::factory()->create(['name' => 'Khomarudin']);
    $kepala->syncRoles(['Organik']);
    $kepala->profile()->update(['full_name' => 'Khomarudin, S.ST', 'nip' => '197512091999011001']);

    $ppk = User::factory()->create(['name' => 'Sulaeman']);
    $ppk->syncRoles(['Organik']);
    $ppk->profile()->update(['full_name' => 'Sulaeman, S.Stat', 'nip' => '198204012006041015']);

    $bendahara = User::factory()->create(['name' => 'Khusnul']);
    $bendahara->syncRoles(['Organik']);
    $bendahara->profile()->update(['full_name' => 'Khusnul Khotimah, S.Stat', 'nip' => '198610252009022009']);

    $kasubag = User::factory()->create(['name' => 'Ahmad']);
    $kasubag->syncRoles(['Organik']);
    $kasubag->profile()->update(['full_name' => 'Ahmad Fauzi, S.Tr.Stat', 'nip' => '199001012012011002']);

    $this->actingAs($admin);

    Livewire::test(SystemSettingsPage::class)
        ->assertSuccessful()
        ->fillForm([
            'kepala_user_id' => $kepala->id,
            'ppk_user_id' => $ppk->id,
            'bendahara_user_id' => $bendahara->id,
            'kasubag_umum_user_id' => $kasubag->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(SystemSettings::class);
    expect($settings->kepala_user_id)->toBe($kepala->id)
        ->and($settings->cert_signer_name)->toBe('Khomarudin, S.ST')
        ->and($settings->cert_signer_nip)->toBe('197512091999011001')
        ->and($settings->ppk_user_id)->toBe($ppk->id)
        ->and($settings->ppk_name)->toBe('Sulaeman, S.Stat')
        ->and($settings->bendahara_user_id)->toBe($bendahara->id)
        ->and($settings->bendahara_name)->toBe('Khusnul Khotimah, S.Stat')
        ->and($settings->kasubag_umum_user_id)->toBe($kasubag->id)
        ->and($settings->kasubag_umum_name)->toBe('Ahmad Fauzi, S.Tr.Stat')
        ->and($settings->kasubag_umum_nip)->toBe('199001012012011002');
});
