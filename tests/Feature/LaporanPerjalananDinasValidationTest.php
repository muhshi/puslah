<?php

namespace Tests\Feature;

use App\Models\LaporanPerjalananDinas;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanPerjalananDinasValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        \Illuminate\Support\Facades\Gate::before(fn () => true);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    private function createSuratTugas(User $user, Survey $survey, string $waktuMulai, string $waktuSelesai, int $urut = 1): SuratTugas
    {
        return SuratTugas::create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'nomor_surat' => "B-" . str_pad($urut, 4, '0', STR_PAD_LEFT) . "/33210/KP.650/2026",
            'nomor_urut' => $urut,
            'kode_klasifikasi' => 'KP.650',
            'jabatan' => 'Staff',
            'keperluan' => 'Pengawasan Lapangan',
            'tanggal' => substr($waktuMulai, 0, 10),
            'waktu_mulai' => $waktuMulai,
            'waktu_selesai' => $waktuSelesai,
            'status' => 'pending',
            'signer_name' => 'Kepala BPS',
            'signer_nip' => '198001012000011001',
            'signer_title' => 'Kepala BPS',
            'signer_city' => 'Demak',
        ]);
    }

    private function createLpd(SuratTugas $st, string $tanggal): LaporanPerjalananDinas
    {
        return LaporanPerjalananDinas::create([
            'surat_tugas_id' => $st->id,
            'nomor_surat_tugas' => $st->nomor_surat,
            'tujuan' => $st->keperluan,
            'tanggal_kunjungan' => $tanggal,
            'uraian_kegiatan' => '<p>Uraian kegiatan test</p>',
        ]);
    }

    /** @test */
    public function it_returns_existing_lpd_dates_for_a_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $survey = Survey::create(['name' => 'Survei Pertanian']);

        $st1 = $this->createSuratTugas($user1, $survey, '2026-08-10 08:00:00', '2026-08-10 16:00:00', 1);
        $st2 = $this->createSuratTugas($user1, $survey, '2026-08-12 08:00:00', '2026-08-12 16:00:00', 2);
        $st3 = $this->createSuratTugas($user2, $survey, '2026-08-10 08:00:00', '2026-08-10 16:00:00', 3);

        $lpd1 = $this->createLpd($st1, '2026-08-10');
        $lpd2 = $this->createLpd($st2, '2026-08-12');
        $lpd3 = $this->createLpd($st3, '2026-08-10');

        $datesUser1 = LaporanPerjalananDinas::getExistingDatesForUser($user1->id);
        $this->assertCount(2, $datesUser1);
        $this->assertContains('2026-08-10', $datesUser1);
        $this->assertContains('2026-08-12', $datesUser1);

        // Excluding lpd1 when editing
        $datesExcluding = LaporanPerjalananDinas::getExistingDatesForUser($user1->id, $lpd1->id);
        $this->assertCount(1, $datesExcluding);
        $this->assertEquals(['2026-08-12'], $datesExcluding);

        // User2 only has one date
        $datesUser2 = LaporanPerjalananDinas::getExistingDatesForUser($user2->id);
        $this->assertEquals(['2026-08-10'], $datesUser2);
    }

    /** @test */
    public function it_detects_duplicate_lpd_for_same_user_and_same_date()
    {
        $user = User::factory()->create();
        $survey = Survey::create(['name' => 'Survei Ubinan']);

        $st1 = $this->createSuratTugas($user, $survey, '2026-09-01 08:00:00', '2026-09-01 16:00:00', 1);
        $lpd1 = $this->createLpd($st1, '2026-09-01');

        // Check duplicate on same date
        $duplicate = LaporanPerjalananDinas::getDuplicateForUser($user->id, '2026-09-01');
        $this->assertNotNull($duplicate);
        $this->assertEquals($lpd1->id, $duplicate->id);

        // Exclude current record
        $duplicateExcluded = LaporanPerjalananDinas::getDuplicateForUser($user->id, '2026-09-01', $lpd1->id);
        $this->assertNull($duplicateExcluded);

        // Check date with no LPD
        $freeDate = LaporanPerjalananDinas::getDuplicateForUser($user->id, '2026-09-02');
        $this->assertNull($freeDate);
    }

    /** @test */
    public function different_users_can_have_lpd_on_same_date()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $survey = Survey::create(['name' => 'Survei Ekonomi']);

        $stA = $this->createSuratTugas($userA, $survey, '2026-09-05 08:00:00', '2026-09-05 16:00:00', 1);
        $this->createLpd($stA, '2026-09-05');

        // UserB should NOT be considered duplicate on 2026-09-05
        $duplicateUserB = LaporanPerjalananDinas::getDuplicateForUser($userB->id, '2026-09-05');
        $this->assertNull($duplicateUserB);
    }

    /** @test */
    public function it_determines_first_available_date_in_surat_tugas_range()
    {
        $user = User::factory()->create();
        $survey = Survey::create(['name' => 'Survei KSA']);

        // User already has an LPD on 2026-09-10
        $stOld = $this->createSuratTugas($user, $survey, '2026-09-10 08:00:00', '2026-09-10 16:00:00', 1);
        $this->createLpd($stOld, '2026-09-10');

        // New ST spans 2026-09-10 to 2026-09-12
        $stMulti = $this->createSuratTugas($user, $survey, '2026-09-10 08:00:00', '2026-09-12 16:00:00', 2);

        // determineAvailableDate should skip 2026-09-10 and return 2026-09-11
        $availableDate = LaporanPerjalananDinas::determineAvailableDate($stMulti);
        $this->assertEquals('2026-09-11', $availableDate);

        // If 2026-09-11 and 2026-09-12 are also taken
        $stExtra1 = $this->createSuratTugas($user, $survey, '2026-09-11 08:00:00', '2026-09-11 16:00:00', 3);
        $this->createLpd($stExtra1, '2026-09-11');

        $stExtra2 = $this->createSuratTugas($user, $survey, '2026-09-12 08:00:00', '2026-09-12 16:00:00', 4);
        $this->createLpd($stExtra2, '2026-09-12');

        // Now all dates 10, 11, 12 are taken
        $noneAvailable = LaporanPerjalananDinas::determineAvailableDate($stMulti);
        $this->assertNull($noneAvailable);
    }

    /** @test */
    public function it_fails_form_validation_when_creating_lpd_with_duplicate_date()
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_laporan::perjalanan::dinas', 'guard_name' => 'web']);
        $user->givePermissionTo('create_laporan::perjalanan::dinas');

        $survey = Survey::create(['name' => 'Survei Susenas']);

        $st1 = $this->createSuratTugas($user, $survey, '2026-09-15 08:00:00', '2026-09-15 16:00:00', 1);
        $this->createLpd($st1, '2026-09-15');

        $st2 = $this->createSuratTugas($user, $survey, '2026-09-15 08:00:00', '2026-09-16 16:00:00', 2);

        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\LaporanPerjalananDinasResource\Pages\CreateLaporanPerjalananDinas::class)
            ->fillForm([
                'surat_tugas_id' => $st2->id,
                'nomor_surat_tugas' => $st2->nomor_surat,
                'tujuan' => $st2->keperluan,
                'tanggal_kunjungan' => '2026-09-15', // duplicate date!
                'uraian_kegiatan' => 'Kegiatan pengawasan lapangan',
            ])
            ->call('create')
            ->assertHasFormErrors(['tanggal_kunjungan']);
    }

    /** @test */
    public function it_allows_creating_lpd_with_available_date()
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $survey = Survey::create(['name' => 'Survei Sakernas']);

        $st1 = $this->createSuratTugas($user, $survey, '2026-09-20 08:00:00', '2026-09-20 16:00:00', 1);
        $this->createLpd($st1, '2026-09-20');

        $st2 = $this->createSuratTugas($user, $survey, '2026-09-21 08:00:00', '2026-09-21 16:00:00', 2);

        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\LaporanPerjalananDinasResource\Pages\CreateLaporanPerjalananDinas::class)
            ->fillForm([
                'surat_tugas_id' => $st2->id,
                'nomor_surat_tugas' => $st2->nomor_surat,
                'tujuan' => $st2->keperluan,
                'tanggal_kunjungan' => '2026-09-21', // Available date!
                'uraian_kegiatan' => 'Kegiatan pendataan Sakernas di lapangan',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('laporan_perjalanan_dinas', [
            'surat_tugas_id' => $st2->id,
        ]);
        $createdLpd = LaporanPerjalananDinas::where('surat_tugas_id', $st2->id)->first();
        $this->assertNotNull($createdLpd);
        $this->assertEquals('2026-09-21', $createdLpd->tanggal_kunjungan->toDateString());
    }

    /** @test */
    public function it_allows_updating_existing_lpd_with_its_own_date()
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $survey = Survey::create(['name' => 'Survei Sakernas']);

        $st = $this->createSuratTugas($user, $survey, '2026-09-22 08:00:00', '2026-09-22 16:00:00', 1);
        $lpd = $this->createLpd($st, '2026-09-22');

        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\LaporanPerjalananDinasResource\Pages\EditLaporanPerjalananDinas::class, [
            'record' => $lpd->getKey(),
        ])
            ->fillForm([
                'tanggal_kunjungan' => '2026-09-22', // Same date as existing record
                'uraian_kegiatan' => '<p>Updated uraian kegiatan</p>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $lpd->refresh();
        $this->assertEquals('2026-09-22', $lpd->tanggal_kunjungan->toDateString());
        $this->assertStringContainsString('Updated uraian kegiatan', $lpd->uraian_kegiatan);
    }

    /** @test */
    public function it_allows_multiple_lpds_for_same_surat_tugas_on_different_dates()
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $survey = Survey::create([
            'name' => 'Survei Multiday',
            'start_date' => '2026-09-25',
            'end_date' => '2026-09-28',
        ]);

        $st = $this->createSuratTugas($user, $survey, '2026-09-25 08:00:00', '2026-09-28 16:00:00', 1);

        // Create first LPD for 2026-09-25
        $this->createLpd($st, '2026-09-25');

        // Create second LPD for 2026-09-26 for the SAME Surat Tugas
        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\LaporanPerjalananDinasResource\Pages\CreateLaporanPerjalananDinas::class)
            ->fillForm([
                'surat_tugas_id' => $st->id,
                'nomor_surat_tugas' => $st->nomor_surat,
                'tujuan' => $st->keperluan,
                'tanggal_kunjungan' => '2026-09-26', // Different date within range!
                'uraian_kegiatan' => 'Kegiatan hari ke-2 pada surat tugas yang sama',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals(2, $st->laporanPerjalananDinas()->count());
    }

    /** @test */
    public function it_fails_validation_when_tanggal_kunjungan_is_outside_range()
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $survey = Survey::create([
            'name' => 'Survei Range Test',
            'start_date' => '2026-09-25',
            'end_date' => '2026-09-28',
        ]);

        $st = $this->createSuratTugas($user, $survey, '2026-09-25 08:00:00', '2026-09-28 16:00:00', 1);

        $this->actingAs($user);

        // Date before range: 2026-09-24
        \Livewire\Livewire::test(\App\Filament\Resources\LaporanPerjalananDinasResource\Pages\CreateLaporanPerjalananDinas::class)
            ->fillForm([
                'surat_tugas_id' => $st->id,
                'nomor_surat_tugas' => $st->nomor_surat,
                'tujuan' => $st->keperluan,
                'tanggal_kunjungan' => '2026-09-24', // Before range!
                'uraian_kegiatan' => 'Test sebelum rentang',
            ])
            ->call('create')
            ->assertHasFormErrors(['tanggal_kunjungan']);

        // Date after range: 2026-09-29
        \Livewire\Livewire::test(\App\Filament\Resources\LaporanPerjalananDinasResource\Pages\CreateLaporanPerjalananDinas::class)
            ->fillForm([
                'surat_tugas_id' => $st->id,
                'nomor_surat_tugas' => $st->nomor_surat,
                'tujuan' => $st->keperluan,
                'tanggal_kunjungan' => '2026-09-29', // After range!
                'uraian_kegiatan' => 'Test setelah rentang',
            ])
            ->call('create')
            ->assertHasFormErrors(['tanggal_kunjungan']);
    }
}


