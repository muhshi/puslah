<?php

namespace Tests\Feature;

use App\Exports\PelatihanKelasTemplateExport;
use App\Filament\Resources\PelatihanResource;
use App\Filament\Resources\PelatihanResource\Pages\CreatePelatihan;
use App\Filament\Resources\PelatihanResource\Pages\EditPelatihan;
use App\Filament\Resources\PelatihanResource\Pages\ListPelatihans;
use App\Filament\Resources\SurveyResource;
use App\Filament\Resources\SurveyResource\Pages\ListSurveys;
use App\Filament\Resources\SurveyResource\RelationManagers\ParticipantsRelationManager;
use App\Imports\PelatihanKelasImport;
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
                'mak' => '524113',
                'biaya_transport' => 120000,
                'biaya_uang_harian' => 150000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pelatihan = Survey::where('name', 'Pelatihan Susenas Maret 2026')->first();
        $this->assertNotNull($pelatihan);
        $this->assertTrue($pelatihan->is_pelatihan);
        $this->assertSame('Hotel Patra Semarang', $pelatihan->lokasi);
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

        // Test Export
        $export = new PelatihanKelasTemplateExport($pelatihan);
        $headings = $export->headings();
        $this->assertSame(['ID', 'Nama', 'Email', 'Role (PPL/PML/Inda/Panitia)', 'Kelas', 'Asal', 'Tujuan'], $headings);

        $collection = $export->collection();
        $this->assertCount(2, $collection);
        $this->assertSame($su1->id, $collection[0]['id']);
        $this->assertSame('Dewi Sartika', $collection[0]['nama']);

        // Test Import using actual Excel file
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['ID', 'Nama', 'Email', 'Role (PPL/PML/Inda/Panitia)', 'Kelas', 'Asal', 'Tujuan'],
            [$su1->id, 'Dewi Sartika', 'dewi@example.com', 'Panitia', 'Kelas C', 'Guntur', 'Yogyakarta'],
            [$su2->id, 'Ahmad Dahlan', 'ahmad@example.com', 'Inda', 'Kelas B', 'Sayung', 'Solo'],
            [$suOther->id, 'User Luar', 'luar@example.com', 'Panitia', 'Kelas D', 'Kudus', 'Semarang'],
        ]);

        $filePath = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $import = new PelatihanKelasImport($pelatihan);
        Excel::import($import, $filePath);
        @unlink($filePath);

        $this->assertSame(2, $import->success);
        $this->assertSame(1, $import->skipped);

        // Assert updates in database
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

        // Assert suOther was NOT modified
        $suOther->refresh();
        $this->assertSame('PPL', $suOther->role_pelatihan);
        $this->assertSame('Kelas A', $suOther->kelas);
    }
}
