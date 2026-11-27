<?php

namespace Tests\Feature;

use App\Exports\PelatihanLkPenilaianTemplateExport;
use App\Exports\PelatihanPenilaianPlatformExport;
use App\Filament\Pages\LembarKerjaPenilaian;
use App\Imports\PelatihanLkPenilaianImport;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\PelatihanPenilaianService;
use App\Settings\SystemSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PelatihanPenilaianTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Organik', 'guard_name' => 'web']);

        SystemSettings::fake(SystemSettings::defaults());
        Gate::before(fn () => true);

        $this->admin = User::factory()->create(['name' => 'Admin']);
        $this->admin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
    }

    public function test_penilaian_service_separates_inda_panitia_and_resolves_mitra_vs_organik(): void
    {
        $survey = Survey::create([
            'name' => 'Pelatihan SPKRT 2026',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'is_active' => true,
        ]);

        // Mitra PPL with NIK from SIPETRA
        $mitra = User::factory()->create(['name' => 'Siti Mitra']);
        $mitra->assignRole('Mitra');
        UserProfile::updateOrCreate(['user_id' => $mitra->id], [
            'full_name' => 'Siti Mitra',
            'address' => 'Demak',
        ]);
        SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $mitra->id,
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
        ]);

        // Mock SipetraUserDataService to return NIK for Siti Mitra
        $this->mock(\App\Services\SipetraUserDataService::class, function ($mock) use ($mitra): void {
            $mock->shouldReceive('fetchForUsers')
                ->andReturn([
                    $mitra->id => (object) ['nik' => '3321010101010001'],
                ]);
        });

        // Mitra PML without NIK -> nip_nik must be empty string ''
        $mitraNoNik = User::factory()->create(['name' => 'Budi Tanpa NIK']);
        $mitraNoNik->assignRole('Mitra');
        UserProfile::updateOrCreate(['user_id' => $mitraNoNik->id], [
            'full_name' => 'Budi Tanpa NIK',
            'sobat_id' => '332199999',
            'address' => 'Sayung',
        ]);
        SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $mitraNoNik->id,
            'role_pelatihan' => 'PML',
            'kelas' => 'Kelas A',
        ]);

        // Organik PML -> Badan Pusat Statistik, internal
        $organik = User::factory()->create(['name' => 'Andi Organik']);
        $organik->syncRoles(['Organik']);
        UserProfile::updateOrCreate(['user_id' => $organik->id], [
            'full_name' => 'Andi Organik',
            'nip' => '199001012015031001',
            'address' => 'Demak Kota',
        ]);
        SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $organik->id,
            'role_pelatihan' => 'PML',
            'kelas' => 'Kelas A',
        ]);

        // Inda & Panitia -> must NOT appear in assessment rows, only in class_officials
        $inda = User::factory()->create(['name' => 'Instruktur Satu']);
        $inda->syncRoles(['Organik']);
        UserProfile::updateOrCreate(['user_id' => $inda->id], ['full_name' => 'Instruktur Satu']);
        SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $inda->id,
            'role_pelatihan' => 'Inda',
            'kelas' => 'Kelas A',
        ]);

        $panitia = User::factory()->create(['name' => 'Panitia Satu']);
        $panitia->syncRoles(['Organik']);
        UserProfile::updateOrCreate(['user_id' => $panitia->id], ['full_name' => 'Panitia Satu']);
        SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $panitia->id,
            'role_pelatihan' => 'Panitia',
            'kelas' => 'Kelas A',
        ]);

        $dataset = app(PelatihanPenilaianService::class)->build($survey);

        $this->assertCount(3, $dataset['rows']);
        $this->assertSame(['Instruktur Satu'], $dataset['class_officials']['Kelas A']['inda']);
        $this->assertSame(['Panitia Satu'], $dataset['class_officials']['Kelas A']['panitia']);

        // PPL comes first (Siti Mitra)
        $this->assertSame('Siti Mitra', $dataset['rows'][0]['nama']);
        $this->assertSame('3321010101010001', $dataset['rows'][0]['nip_nik']);
        $this->assertSame('Mitra', $dataset['rows'][0]['instansi']);
        $this->assertSame('eksternal', $dataset['rows'][0]['tipe_peserta']);
        $this->assertSame(1, $dataset['rows'][0]['is_lulus']);

        // PML Organik
        $organikRow = collect($dataset['rows'])->firstWhere('nama', 'Andi Organik');
        $this->assertSame('199001012015031001', $organikRow['nip_nik']);
        $this->assertSame('Badan Pusat Statistik', $organikRow['instansi']);
        $this->assertSame('internal', $organikRow['tipe_peserta']);

        // PML Mitra without NIK
        $noNikRow = collect($dataset['rows'])->firstWhere('nama', 'Budi Tanpa NIK');
        $this->assertSame('', $noNikRow['nip_nik']);
    }

    public function test_lk_template_export_import_roundtrip_and_platform_export(): void
    {
        $survey = Survey::create([
            'name' => 'Pelatihan Sensus',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['name' => 'Peserta Test']);
        $user->assignRole('Mitra');
        UserProfile::updateOrCreate(['user_id' => $user->id], [
            'full_name' => 'Peserta Test',
            'address' => 'Mranggen',
        ]);
        $su = SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
        ]);

        $this->mock(\App\Services\SipetraUserDataService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('fetchForUsers')
                ->andReturn([
                    $user->id => (object) ['nik' => '3321000000000009'],
                ]);
        });

        // 1. Download template LK to temp file
        $tempPath = tempnam(sys_get_temp_dir(), 'lk_test_').'.xlsx';
        $raw = Excel::raw(new PelatihanLkPenilaianTemplateExport($survey), \Maatwebsite\Excel\Excel::XLSX);
        file_put_contents($tempPath, $raw);

        // 2. Edit component scores in the Excel template (row 8: H=pre_test, I=post_test, J=pendalaman, K=sikap_perilaku)
        $spreadsheet = IOFactory::load($tempPath);
        $sheet = $spreadsheet->getSheet(0);
        $sheet->setCellValue('H8', 80); // 8% -> 6.4
        $sheet->setCellValue('I8', 90); // 8% -> 7.2
        $sheet->setCellValue('J8', 85); // 64% -> 54.4
        $sheet->setCellValue('K8', 95); // 20% -> 19.0 => total = 87.0
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempPath);

        // 3. Import back into system
        $importer = new PelatihanLkPenilaianImport($survey);
        $importer->importFile($tempPath);
        @unlink($tempPath);

        $this->assertSame(1, $importer->updated);
        $su->refresh();
        $this->assertSame(87, $su->score);
        $this->assertTrue((bool) $su->is_lulus);

        // 4. Platform Export has exact 7 columns
        $platformExport = new PelatihanPenilaianPlatformExport($survey);
        $this->assertSame([
            'nip_nik',
            'nama',
            'instansi',
            'alamat',
            'tipe_peserta',
            'nilai',
            'is_lulus',
        ], $platformExport->headings());

        $rows = $platformExport->array();
        $this->assertCount(1, $rows);
        $this->assertSame(['3321000000000009', 'Peserta Test', 'Mitra', 'Mranggen', 'eksternal', 87.0, 1], $rows[0]);
    }

    public function test_lembar_kerja_penilaian_page_saves_inline_scores(): void
    {
        $survey = Survey::create([
            'name' => 'Pelatihan UI Test',
            'dasar_surat' => 'DIPA 2026',
            'is_pelatihan' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['name' => 'Peserta Inline']);
        $user->assignRole('Mitra');
        UserProfile::updateOrCreate(['user_id' => $user->id], ['full_name' => 'Peserta Inline']);
        $su = SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
        ]);

        Livewire::withQueryParams(['survey_id' => $survey->id])
            ->test(LembarKerjaPenilaian::class)
            ->assertSee('Peserta Inline')
            ->set("scoresInput.{$su->id}.pre_test", 100)
            ->set("scoresInput.{$su->id}.post_test", 100)
            ->set("scoresInput.{$su->id}.pendalaman", 80)
            ->set("scoresInput.{$su->id}.sikap_perilaku", 90)
            ->call('saveRowScore', $su->id);

        $su->refresh();
        // 100*0.08 + 100*0.08 + 80*0.64 + 90*0.20 = 8 + 8 + 51.2 + 18 = 85.2 -> 85
        $this->assertSame(85, $su->score);
    }
}
