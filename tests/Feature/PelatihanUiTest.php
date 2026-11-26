<?php

namespace Tests\Feature;

use App\Exports\PelatihanKelasTemplateExport;
use App\Filament\Resources\PelatihanResource;
use App\Filament\Resources\PelatihanResource\Pages\CreatePelatihan;
use App\Filament\Resources\PelatihanResource\Pages\EditPelatihan;
use App\Filament\Resources\PelatihanResource\Pages\ListPelatihans;
use App\Filament\Resources\SuratTugasResource;
use App\Filament\Resources\SuratTugasResource\Pages\CreateBulkSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\CreateSuratTugas;
use App\Filament\Resources\SuratTugasResource\Support\SuratTugasFormFactory;
use App\Filament\Resources\SurveyResource;
use App\Filament\Resources\SurveyResource\Pages\ListSurveys;
use App\Filament\Resources\SurveyResource\RelationManagers\ParticipantsRelationManager;
use App\Imports\PelatihanKelasImport;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Settings\SystemSettings;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PelatihanUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Organik', 'guard_name' => 'web']);

        SystemSettings::fake(SystemSettings::defaults());

        Gate::before(fn () => true);

        $this->superAdmin = User::factory()->create(['name' => 'Admin User']);
        $this->superAdmin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);
    }

    public function test_scoping_separates_kegiatan_and_pelatihan(): void
    {
        $kegiatan = Survey::create([
            'name' => 'Kegiatan Survei Ubinan 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => false,
            'is_active' => true,
        ]);

        $pelatihan = Survey::create([
            'name' => 'Pelatihan Petugas Sensus 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'lokasi' => 'Hotel Grasia Semarang',
            'kota_kegiatan' => 'Semarang',
            'is_active' => true,
        ]);

        // SurveyResource only queries non-pelatihan
        $surveyQueryIds = SurveyResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($kegiatan->id, $surveyQueryIds);
        $this->assertNotContains($pelatihan->id, $surveyQueryIds);

        // PelatihanResource only queries pelatihan
        $pelatihanQueryIds = PelatihanResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($pelatihan->id, $pelatihanQueryIds);
        $this->assertNotContains($kegiatan->id, $pelatihanQueryIds);

        // Livewire UI listing check
        Livewire::test(ListSurveys::class)
            ->assertSee($kegiatan->name)
            ->assertDontSee($pelatihan->name);

        Livewire::test(ListPelatihans::class)
            ->assertSee($pelatihan->name)
            ->assertDontSee($kegiatan->name);
    }

    public function test_create_pelatihan_page_mutates_and_sets_is_pelatihan_true(): void
    {
        Livewire::test(CreatePelatihan::class)
            ->assertFormFieldDoesNotExist('ppk_user_id')
            ->assertFormFieldDoesNotExist('bendahara_user_id')
            ->assertFormFieldDoesNotExist('penyelenggara_user_id')
            ->assertFormFieldDoesNotExist('link_materi')
            ->fillForm([
                'name' => 'Pelatihan Susenas Maret 2026',
                'description' => 'Pelatihan calon petugas Susenas',
                'dasar_surat' => 'DIPA BPS Kabupaten Demak 2026',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-04',
                'lokasi' => 'Hotel Patra Semarang',
                'kota_kegiatan' => 'Semarang',
                'is_active' => true,
                'is_multiple' => false,
                'biaya_transport' => 120000,
                'biaya_uang_harian' => 150000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pelatihan = Survey::where('name', 'Pelatihan Susenas Maret 2026')->first();
        $this->assertNotNull($pelatihan);
        $this->assertTrue($pelatihan->is_pelatihan);
        $this->assertSame('Hotel Patra Semarang', $pelatihan->lokasi);
        $this->assertSame('PROGRAM PENYEDIAAN DAN PELAYANAN INFORMASI STATISTIK (054.01.GG)', $pelatihan->program);
        $this->assertSame('PENYEDIAAN DAN PENGEMBANGAN STATISTIK HARGA (2903)', $pelatihan->kegiatan_dipa);
        $this->assertSame('DATA DAN INFORMASI PUBLIK (2903.BMA)', $pelatihan->klasifikasi_ro);
        $this->assertSame('PUBLIKASI/LAPORAN SPKRT (2903.BMA.007)', $pelatihan->rincian_output);
        $this->assertSame('PERSIAPAN (2903.BMA.009.051A)', $pelatihan->komponen);
        $this->assertSame('BELANJA PERJALANAN DINAS DALAM KOTA (524113)', $pelatihan->mak);
        $this->assertSame(120000, $pelatihan->biaya_transport);
        $this->assertSame(150000, $pelatihan->biaya_uang_harian);
    }

    public function test_participants_relation_manager_smart_defaults_on_mass_add(): void
    {
        $pelatihan = Survey::create([
            'name' => 'Pelatihan Petugas Sakernas 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'lokasi' => 'Semarang Hotel',
            'kota_kegiatan' => 'Semarang',
            'is_active' => true,
        ]);

        $mitraUser = User::factory()->create(['name' => 'Mitra Budi', 'email' => 'budi@mitra.test']);
        $mitraUser->assignRole('Mitra');
        $mitraUser->profile()->updateOrCreate(
            ['user_id' => $mitraUser->id],
            [
                'full_name' => 'Mitra Budi',
                'address' => 'Kecamatan Sayung, Kabupaten Demak',
            ]
        );

        $organikUser = User::factory()->create(['name' => 'Pegawai Siti', 'email' => 'siti@organik.test']);
        $organikUser->assignRole('Organik');
        $organikUser->profile()->updateOrCreate(
            ['user_id' => $organikUser->id],
            [
                'full_name' => 'Pegawai Siti',
                'address' => 'Kecamatan Karangtengah, Kabupaten Demak',
            ]
        );

        Livewire::test(ParticipantsRelationManager::class, [
            'ownerRecord' => $pelatihan,
            'pageClass' => EditPelatihan::class,
        ])
            ->callTableAction('massAdd', data: [
                'mitra_users' => [$mitraUser->id],
                'pegawai_bps_users' => [$organikUser->id],
                'notes' => 'Batch 1',
            ])
            ->assertHasNoTableActionErrors();

        $suMitra = SurveyUser::where('survey_id', $pelatihan->id)->where('user_id', $mitraUser->id)->first();
        $this->assertNotNull($suMitra);
        $this->assertSame('PPL', $suMitra->role_pelatihan);
        $this->assertSame('Kelas A', $suMitra->kelas);
        $this->assertSame('Semarang', $suMitra->tujuan);
        $this->assertSame('Sayung', $suMitra->asal);

        $suOrganik = SurveyUser::where('survey_id', $pelatihan->id)->where('user_id', $organikUser->id)->first();
        $this->assertNotNull($suOrganik);
        $this->assertSame('PML', $suOrganik->role_pelatihan);
        $this->assertSame('Kelas A', $suOrganik->kelas);
        $this->assertSame('Semarang', $suOrganik->tujuan);
        $this->assertSame('Karangtengah', $suOrganik->asal);
    }

    public function test_participants_relation_manager_bulk_actions(): void
    {
        $pelatihan = Survey::create([
            'name' => 'Pelatihan Komoditas 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'kota_kegiatan' => 'Semarang',
            'is_active' => true,
        ]);

        $u1 = User::factory()->create(['name' => 'Peserta Satu']);
        $u2 = User::factory()->create(['name' => 'Peserta Dua']);

        $su1 = SurveyUser::create([
            'survey_id' => $pelatihan->id,
            'user_id' => $u1->id,
            'status' => 'registered',
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
            'asal' => 'Demak',
            'tujuan' => 'Semarang',
        ]);

        $su2 = SurveyUser::create([
            'survey_id' => $pelatihan->id,
            'user_id' => $u2->id,
            'status' => 'registered',
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
            'asal' => 'Demak',
            'tujuan' => 'Semarang',
        ]);

        $rm = new ParticipantsRelationManager;
        $rm->ownerRecord = $pelatihan;
        $table = $rm->table(new Table($rm));

        // Test Bulk Action setKelas
        $setKelasAction = $table->getBulkAction('setKelas');
        $this->assertNotNull($setKelasAction);
        $setKelasAction->call([
            'records' => collect([$su1, $su2]),
            'data' => ['kelas' => 'Kelas B'],
        ]);

        $this->assertSame('Kelas B', $su1->fresh()->kelas);
        $this->assertSame('Kelas B', $su2->fresh()->kelas);

        // Test Bulk Action setRolePelatihan
        $setRoleAction = $table->getBulkAction('setRolePelatihan');
        $this->assertNotNull($setRoleAction);
        $setRoleAction->call([
            'records' => collect([$su1]),
            'data' => ['role_pelatihan' => 'Inda'],
        ]);

        $this->assertSame('Inda', $su1->fresh()->role_pelatihan);
        $this->assertSame('PPL', $su2->fresh()->role_pelatihan);

        // Test Bulk Action setAsalTujuan
        $setAsalTujuanAction = $table->getBulkAction('setAsalTujuan');
        $this->assertNotNull($setAsalTujuanAction);
        $setAsalTujuanAction->call([
            'records' => collect([$su1, $su2]),
            'data' => ['asal' => 'Mranggen', 'tujuan' => 'Solo'],
        ]);

        $this->assertSame('Mranggen', $su1->fresh()->asal);
        $this->assertSame('Solo', $su1->fresh()->tujuan);
        $this->assertSame('Mranggen', $su2->fresh()->asal);
        $this->assertSame('Solo', $su2->fresh()->tujuan);
    }

    public function test_pelatihan_kelas_export_and_import_round_trip(): void
    {
        $pelatihan = Survey::create([
            'name' => 'Pelatihan Potensi Desa 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'kota_kegiatan' => 'Semarang',
            'is_active' => true,
        ]);

        $otherPelatihan = Survey::create([
            'name' => 'Pelatihan Lain',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'is_active' => true,
        ]);

        $user1 = User::factory()->create(['name' => 'Dewi Sartika', 'email' => 'dewi@example.com']);
        $user2 = User::factory()->create(['name' => 'Ahmad Dahlan', 'email' => 'ahmad@example.com']);
        $userOther = User::factory()->create(['name' => 'User Luar', 'email' => 'luar@example.com']);

        $su1 = SurveyUser::create([
            'survey_id' => $pelatihan->id,
            'user_id' => $user1->id,
            'status' => 'registered',
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
            'asal' => 'Demak',
            'tujuan' => 'Semarang',
        ]);

        $su2 = SurveyUser::create([
            'survey_id' => $pelatihan->id,
            'user_id' => $user2->id,
            'status' => 'registered',
            'role_pelatihan' => 'PML',
            'kelas' => 'Kelas A',
            'asal' => 'Wonosalam',
            'tujuan' => 'Semarang',
        ]);

        $suOther = SurveyUser::create([
            'survey_id' => $otherPelatihan->id,
            'user_id' => $userOther->id,
            'status' => 'registered',
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
            'asal' => 'Kudus',
            'tujuan' => 'Semarang',
        ]);

        // Test Export uses visual 'No' (1, 2, ...) instead of internal DB ID
        $export = new PelatihanKelasTemplateExport($pelatihan);
        $headings = $export->headings();
        $this->assertSame(['No', 'Nama', 'Email', 'Role (PPL/PML/Inda/Panitia)', 'Kelas', 'Asal', 'Tujuan'], $headings);

        $collection = $export->collection();
        $this->assertCount(2, $collection);
        $this->assertSame(1, $collection[0]['no']);
        $this->assertSame('Dewi Sartika', $collection[0]['nama']);
        $this->assertSame('dewi@example.com', $collection[0]['email']);

        // Test Import using actual Excel file (using 'No' + Email, plus backwards-compatible legacy 'ID' tenant check)
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['No', 'Nama', 'Email', 'Role (PPL/PML/Inda/Panitia)', 'Kelas', 'Asal', 'Tujuan'],
            [1, 'Dewi Sartika', 'dewi@example.com', 'Panitia', 'Kelas C', 'Guntur', 'Yogyakarta'],
            [2, 'Ahmad Dahlan', 'ahmad@example.com', 'Inda', 'Kelas B', 'Sayung', 'Solo'],
        ]);

        $filePath = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $import = new PelatihanKelasImport($pelatihan);
        Excel::import($import, $filePath);
        @unlink($filePath);

        $this->assertSame(2, $import->success);
        $this->assertSame(2, $import->updated);
        $this->assertSame(0, $import->created);

        // Assert updates in database (no duplicate survey_users created)
        $this->assertSame(2, SurveyUser::where('survey_id', $pelatihan->id)->count());
        $su1->refresh();
        $this->assertSame('Panitia', $su1->role_pelatihan);
        $this->assertSame('Kelas C', $su1->kelas);
        $this->assertSame('Guntur', $su1->asal);
        $this->assertSame('Yogyakarta', $su1->tujuan);

        $su2->refresh();
        $this->assertSame('Inda', $su2->role_pelatihan);
        $this->assertSame('Kelas B', $su2->kelas);
        $this->assertSame('Sayung', $su2->asal);
        $this->assertSame('Solo', $su2->tujuan);

        // Legacy ID column tenant isolation still skips foreign survey_user IDs
        $legacySpreadsheet = new Spreadsheet;
        $legacySpreadsheet->getActiveSheet()->fromArray([
            ['ID', 'Nama', 'Email', 'Role (PPL/PML/Inda/Panitia)', 'Kelas', 'Asal', 'Tujuan'],
            [$suOther->id, 'User Luar', 'luar@example.com', 'Panitia', 'Kelas D', 'Kudus', 'Semarang'],
        ]);
        $legacyPath = tempnam(sys_get_temp_dir(), 'test_legacy_import_').'.xlsx';
        (new Xlsx($legacySpreadsheet))->save($legacyPath);
        $legacyImport = new PelatihanKelasImport($pelatihan);
        Excel::import($legacyImport, $legacyPath);
        @unlink($legacyPath);

        $this->assertSame(0, $legacyImport->success);
        $this->assertSame(1, $legacyImport->skipped);

        // Assert suOther was NOT modified
        $suOther->refresh();
        $this->assertSame('PPL', $suOther->role_pelatihan);
        $this->assertSame('Kelas A', $suOther->kelas);
    }

    public function test_pelatihan_can_be_selected_and_prefilled_in_surat_tugas(): void
    {
        $kegiatan = Survey::create([
            'name' => 'Pendataan Susenas 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => false,
            'is_active' => true,
        ]);

        $pelatihan = Survey::create([
            'name' => 'Pelatihan Petugas Susenas 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'lokasi' => 'Hotel Grasia Semarang',
            'kota_kegiatan' => 'Semarang',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'is_active' => true,
        ]);

        $mitra = User::factory()->create(['name' => 'Mitra Pelatihan']);
        $mitra->assignRole('Mitra');
        $mitra->profile()->updateOrCreate(['user_id' => $mitra->id], [
            'full_name' => 'Mitra Pelatihan',
            'jabatan' => 'Mitra Statistik',
        ]);

        $organik = User::factory()->create(['name' => 'Pegawai Pelatihan']);
        $organik->assignRole('Organik');
        $organik->profile()->updateOrCreate(['user_id' => $organik->id], [
            'full_name' => 'Pegawai Pelatihan',
            'jabatan' => 'Statistisi Ahli Pertama',
        ]);

        SurveyUser::create([
            'survey_id' => $kegiatan->id,
            'user_id' => $mitra->id,
            'status' => 'registered',
        ]);

        SurveyUser::create([
            'survey_id' => $pelatihan->id,
            'user_id' => $mitra->id,
            'status' => 'registered',
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
        ]);

        SurveyUser::create([
            'survey_id' => $pelatihan->id,
            'user_id' => $organik->id,
            'status' => 'registered',
            'role_pelatihan' => 'Panitia',
            'kelas' => 'Kelas A',
        ]);

        // 1. Both Kegiatan and Pelatihan appear in SuratTugas dropdowns with distinct prefixes
        $options = SuratTugasFormFactory::getActiveSurveyOptions(onlyWithRemainingParticipants: true);
        $this->assertSame('[Kegiatan] Pendataan Susenas 2026', $options[$kegiatan->id] ?? null);
        $this->assertSame('[Pelatihan] Pelatihan Petugas Susenas 2026', $options[$pelatihan->id] ?? null);

        // 2. Participant labels for Pelatihan include role_pelatihan and kelas
        $mitraOptions = SuratTugasFormFactory::getSurveyParticipantOptions($pelatihan->id, 'Mitra');
        $organikOptions = SuratTugasFormFactory::getSurveyParticipantOptions($pelatihan->id, 'Organik');
        $this->assertSame('Mitra Pelatihan (Mitra Statistik) — PPL · Kelas A', $mitraOptions[$mitra->id] ?? null);
        $this->assertSame('Pegawai Pelatihan (Statistisi Ahli Pertama) — Panitia · Kelas A', $organikOptions[$organik->id] ?? null);

        // 3. CreateBulkSuratTugas pre-fills Pelatihan details via ?survey_id= and creates SuratTugas
        Livewire::withQueryParams(['survey_id' => $pelatihan->id])
            ->test(CreateBulkSuratTugas::class)
            ->assertSet('data.survey_id', $pelatihan->id)
            ->assertSet('data.keperluan', 'Pelatihan Petugas Susenas 2026')
            ->assertSet('data.tempat_tugas', 'Hotel Grasia Semarang')
            ->assertSet('data.waktu_mulai', '2026-06-10')
            ->assertSet('data.waktu_selesai', '2026-06-12')
            ->fillForm([
                'mitra_user_ids' => [$mitra->id],
                'pegawai_bps_user_ids' => [$organik->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('surat_tugas', [
            'survey_id' => $pelatihan->id,
            'user_id' => $mitra->id,
            'tempat_tugas' => 'Hotel Grasia Semarang',
            'keperluan' => 'Pelatihan Petugas Susenas 2026',
        ]);
        $this->assertDatabaseHas('surat_tugas', [
            'survey_id' => $pelatihan->id,
            'user_id' => $organik->id,
            'tempat_tugas' => 'Hotel Grasia Semarang',
        ]);

        // 4. Overlap error message refers to 'pelatihan'
        $st = SuratTugas::where('survey_id', $pelatihan->id)->where('user_id', $mitra->id)->firstOrFail();
        $this->assertStringContainsString("untuk pelatihan 'Pelatihan Petugas Susenas 2026'", SuratTugas::formatOverlapMessage('Mitra Pelatihan', $st));

        // 5. CreateSuratTugas single page also pre-fills Pelatihan via ?survey_id=
        Livewire::withQueryParams(['survey_id' => $pelatihan->id])
            ->test(CreateSuratTugas::class)
            ->assertSet('data.survey_id', $pelatihan->id)
            ->assertSet('data.keperluan', 'Pelatihan Petugas Susenas 2026')
            ->assertSet('data.tempat_tugas', 'Hotel Grasia Semarang')
            ->assertSet('data.waktu_mulai', '2026-06-10')
            ->assertSet('data.waktu_selesai', '2026-06-12');

        // 6. PelatihanResource table exposes Buat Surat Tugas action pointing to CreateBulkSuratTugas
        Livewire::test(ListPelatihans::class)
            ->assertTableActionExists('buatSuratTugas', record: $pelatihan)
            ->assertTableActionHasUrl(
                'buatSuratTugas',
                SuratTugasResource::getUrl('create-bulk', ['survey_id' => $pelatihan->id]),
                record: $pelatihan
            );

        // 7. EditPelatihan header exposes Buat Surat Tugas action
        Livewire::test(EditPelatihan::class, ['record' => $pelatihan->getRouteKey()])
            ->assertActionExists('buatSuratTugasHeader')
            ->assertActionHasUrl(
                'buatSuratTugasHeader',
                SuratTugasResource::getUrl('create-bulk', ['survey_id' => $pelatihan->id])
            );
    }

    public function test_edit_pelatihan_auto_fills_empty_and_legacy_dipa_defaults_without_overwriting_custom_values(): void
    {
        $legacyPelatihan = Survey::create([
            'name' => 'Pelatihan SPKRT Legacy Null DIPA',
            'dasar_surat' => 'DIPA BPS Demak 2026',
            'start_date' => '2026-11-24',
            'end_date' => '2026-11-25',
            'lokasi' => 'Hotel Semarang',
            'kota_kegiatan' => 'Semarang',
            'is_pelatihan' => true,
            'is_active' => true,
            'mak' => '524113',
        ]);

        // 1. Opening Edit on legacy/null DIPA record auto-populates all 6 DIPA fields + tarif
        Livewire::test(EditPelatihan::class, ['record' => $legacyPelatihan->getRouteKey()])
            ->assertSet('data.program', Survey::DEFAULT_PELATIHAN_DIPA['program'])
            ->assertSet('data.kegiatan_dipa', Survey::DEFAULT_PELATIHAN_DIPA['kegiatan_dipa'])
            ->assertSet('data.klasifikasi_ro', Survey::DEFAULT_PELATIHAN_DIPA['klasifikasi_ro'])
            ->assertSet('data.rincian_output', Survey::DEFAULT_PELATIHAN_DIPA['rincian_output'])
            ->assertSet('data.komponen', Survey::DEFAULT_PELATIHAN_DIPA['komponen'])
            ->assertSet('data.mak', Survey::DEFAULT_PELATIHAN_DIPA['mak'])
            ->assertSet('data.biaya_transport', 100000)
            ->assertSet('data.biaya_uang_harian', 130000)
            ->call('save')
            ->assertHasNoFormErrors();

        $legacyPelatihan->refresh();
        $this->assertSame(Survey::DEFAULT_PELATIHAN_DIPA['program'], $legacyPelatihan->program);
        $this->assertSame(Survey::DEFAULT_PELATIHAN_DIPA['mak'], $legacyPelatihan->mak);

        // 2. Editing to custom values preserves custom values on subsequent Edit load
        $legacyPelatihan->update([
            'program' => 'PROGRAM CUSTOM (054.01.XX)',
            'mak' => 'BELANJA BAHAN (521211)',
        ]);

        Livewire::test(EditPelatihan::class, ['record' => $legacyPelatihan->getRouteKey()])
            ->assertSet('data.program', 'PROGRAM CUSTOM (054.01.XX)')
            ->assertSet('data.mak', 'BELANJA BAHAN (521211)');
    }

    public function test_mitra_duplicate_names_show_email_and_template_import_enrolls_directly(): void
    {
        $pelatihan = Survey::create([
            'name' => 'Pelatihan SPKRT 2027',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'kota_kegiatan' => 'Semarang',
            'is_active' => true,
        ]);

        // Two Mitra with identical names but distinct emails & kecamatan
        $mitraA = User::factory()->create(['name' => 'Ahmad Fauzi', 'email' => 'fauzi.sayung@mitra.test']);
        $mitraA->assignRole('Mitra');
        $mitraA->profile()->updateOrCreate(
            ['user_id' => $mitraA->id],
            ['full_name' => 'Ahmad Fauzi', 'address' => 'Kecamatan Sayung, Kabupaten Demak', 'nip' => '3321010101010001']
        );

        $mitraB = User::factory()->create(['name' => 'Ahmad Fauzi', 'email' => 'fauzi.mranggen@mitra.test']);
        $mitraB->assignRole('Mitra');
        $mitraB->profile()->updateOrCreate(
            ['user_id' => $mitraB->id],
            ['full_name' => 'Ahmad Fauzi', 'address' => 'Kecamatan Mranggen, Kabupaten Demak', 'nip' => '3321010101010002']
        );

        // Unrelated Organik should NOT be auto-enrolled from Sheet 2 reference sheet
        $organik = User::factory()->create(['name' => 'Inda Dewi', 'email' => 'inda@bps.test']);
        $organik->assignRole('Organik');
        $organik->profile()->updateOrCreate(
            ['user_id' => $organik->id],
            ['full_name' => 'Inda Dewi', 'jabatan' => 'Statistisi']
        );

        // 1. Verify massAdd & importKelas UI expose distinguishing Mitra emails and downloadable template
        $getLabels = new \ReflectionMethod(ParticipantsRelationManager::class, 'userOptionLabels');
        $mitraLabels = $getLabels->invoke(null, 'Mitra');
        $this->assertSame('Ahmad Fauzi (fauzi.sayung@mitra.test · Kec. Sayung)', $mitraLabels[$mitraA->id] ?? null);
        $this->assertSame('Ahmad Fauzi (fauzi.mranggen@mitra.test · Kec. Mranggen)', $mitraLabels[$mitraB->id] ?? null);

        Livewire::test(ParticipantsRelationManager::class, [
            'ownerRecord' => $pelatihan,
            'pageClass' => EditPelatihan::class,
        ])
            ->assertTableActionDoesNotExist('create')
            ->assertTableActionExists('massAdd')
            ->assertTableActionExists('exportKelasTemplate')
            ->assertTableActionExists('importKelas');

        // Verify single-sheet template export does NOT bundle all unrelated users/credentials
        $templateExport = new PelatihanKelasTemplateExport($pelatihan);
        $this->assertSame('Pembagian Kelas', $templateExport->title());
        $this->assertCount(0, $templateExport->collection());
        $this->assertNotEmpty(Excel::raw($templateExport, \Maatwebsite\Excel\Excel::XLSX));

        // 2. Direct Excel template import (without selecting participants beforehand & using visual 'No')
        // Pre-enroll $alreadyAdded via web UI, then include them in Excel too -> must update cleanly with zero duplicates!
        $alreadyAdded = User::factory()->create(['name' => 'Peserta Web', 'email' => 'peserta.web@mitra.test']);
        $alreadyAdded->assignRole('Mitra');
        SurveyUser::create([
            'survey_id' => $pelatihan->id,
            'user_id' => $alreadyAdded->id,
            'status' => 'registered',
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
            'asal' => 'Demak',
            'tujuan' => 'Semarang',
        ]);

        $spreadsheet = new Spreadsheet;
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Pembagian Kelas');
        $sheet1->fromArray([
            ['No', 'Nama', 'Email', 'Role (PPL/PML/Inda/Panitia)', 'Kelas', 'Asal', 'Tujuan'],
            // Row 2: new participant matched uniquely by email despite identical name; blank Asal/Tujuan -> smart defaults
            [1, 'Ahmad Fauzi', 'fauzi.mranggen@mitra.test', 'PPL', 'Kelas B', null, null],
            // Row 3: same user already added via web UI -> updates existing row without creating duplicate
            [2, 'Peserta Web', 'peserta.web@mitra.test', 'PML', 'Kelas C', null, null],
            // Row 4: blank email + ambiguous duplicate name -> must fail with clear reason
            [3, 'Ahmad Fauzi', null, 'PPL', 'Kelas A', 'Demak', 'Semarang'],
            // Row 5: pre-numbered blank row -> silently ignored
            [4, null, null, null, null, null, null],
        ]);

        // Add Sheet 2 reference sheet to ensure multi-sheet workbook import ignores Sheet 2
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Referensi Mitra & Pegawai');
        $sheet2->fromArray([
            ['Nama', 'Email (Copy ke Sheet Pembagian Kelas)', 'Role Sistem', 'NIK / NIP', 'Kecamatan / Asal', 'Jabatan'],
            ['Inda Dewi', 'inda@bps.test', 'Organik', '-', '-', 'Statistisi'],
        ]);

        $filePath = tempnam(sys_get_temp_dir(), 'test_template_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        $import = new PelatihanKelasImport($pelatihan);
        Excel::import($import, $filePath);
        @unlink($filePath);

        $this->assertSame(1, $import->created);
        $this->assertSame(1, $import->updated);
        $this->assertSame(2, $import->success);
        $this->assertSame(1, $import->failed); // Ambiguous name-only row
        $this->assertNotEmpty($import->errors);
        $this->assertStringContainsString('wajib isi kolom Email', $import->errors[0]);

        $enrolled = SurveyUser::where('survey_id', $pelatihan->id)->where('user_id', $mitraB->id)->first();
        $this->assertNotNull($enrolled);
        $this->assertSame('PPL', $enrolled->role_pelatihan);
        $this->assertSame('Kelas B', $enrolled->kelas);
        $this->assertSame('Mranggen', $enrolled->asal);
        $this->assertSame('Semarang', $enrolled->tujuan);

        // Already-enrolled web participant updated cleanly with no duplicate row
        $this->assertSame(1, SurveyUser::where('survey_id', $pelatihan->id)->where('user_id', $alreadyAdded->id)->count());
        $webParticipant = SurveyUser::where('survey_id', $pelatihan->id)->where('user_id', $alreadyAdded->id)->first();
        $this->assertSame('PML', $webParticipant->role_pelatihan);
        $this->assertSame('Kelas C', $webParticipant->kelas);

        // Verify Mitra A and Sheet 2 Organik were NOT enrolled
        $this->assertDatabaseMissing('survey_users', ['survey_id' => $pelatihan->id, 'user_id' => $mitraA->id]);
        $this->assertDatabaseMissing('survey_users', ['survey_id' => $pelatihan->id, 'user_id' => $organik->id]);
    }
}
