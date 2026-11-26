<?php

namespace Tests\Feature;

use App\Filament\Pages\AttendanceRecap;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Leaderboard;
use App\Filament\Pages\MySurveys;
use App\Filament\Pages\PhotoStamp;
use App\Filament\Pages\SystemSettingsPage;
use App\Filament\Resources\DailyActivityResource\Pages\CreateDailyActivity;
use App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities;
use App\Filament\Resources\LaporanLemburResource\Pages\CreateLaporanLembur;
use App\Filament\Resources\LaporanLemburResource\Pages\ListLaporanLemburs;
use App\Filament\Resources\LaporanPerjalananDinasResource\Pages\CreateLaporanPerjalananDinas;
use App\Filament\Resources\LaporanPerjalananDinasResource\Pages\ListLaporanPerjalananDinas;
use App\Filament\Resources\OfficeResource\Pages\CreateOffice;
use App\Filament\Resources\OfficeResource\Pages\ListOffices;
use App\Filament\Resources\PelatihanResource\Pages\CreatePelatihan;
use App\Filament\Resources\PelatihanResource\Pages\ListPelatihans;
use App\Filament\Resources\Shield\RoleResource\Pages\ListRoles;
use App\Filament\Resources\SuratTugasResource\Pages\CreateBulkSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\CreateSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\ListSuratTugas;
use App\Filament\Resources\SuratTugasResource\Pages\ManageBlockedNumbers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Widgets\AttendanceLast7DaysChart;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\DailyActivityCalendarWidget;
use App\Filament\Widgets\LatestSuratTugasWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\SuratTugasChartWidget;
use App\Filament\Widgets\SurveyChartWidget;
use App\Filament\Widgets\TodayAttendanceStats;
use App\Filament\Widgets\TodayPresencePie;
use App\Livewire\AttendanceUnattendedRecap;
use App\Livewire\Presensi;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\User;
use App\Settings\SystemSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentUpgradeSmokeTest extends TestCase
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

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_public_and_guest_routes_respond_as_expected(): void
    {
        $this->get('/')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->get('/presensi')->assertRedirect('/login');
    }

    public function test_super_admin_can_get_admin_panel_and_main_routes(): void
    {
        $this->actingAs($this->superAdmin);

        $routes = [
            '/presensi',
            '/admin',
            '/admin/attendance-recap',
            '/admin/attendance-rules',
            '/admin/attendances',
            '/admin/certificate-templates',
            '/admin/daily-activities',
            '/admin/daily-activities/create',
            '/admin/laporan-lemburs',
            '/admin/laporan-lemburs/create',
            '/admin/laporan-perjalanan-dinas',
            '/admin/laporan-perjalanan-dinas/create',
            '/admin/leaderboard',
            '/admin/leaves',
            '/admin/my-surveys',
            '/admin/offices',
            '/admin/offices/create',
            '/admin/pelatihans',
            '/admin/pelatihans/create',
            '/admin/schedules',
            '/admin/shared-work-reports',
            '/admin/shield/roles',
            '/admin/shifts',
            '/admin/stempel-foto',
            '/admin/surat-tugas',
            '/admin/surat-tugas/create',
            '/admin/surat-tugas/create-bulk',
            '/admin/surat-tugas/manage-blocked-numbers',
            '/admin/survey-users',
            '/admin/surveys',
            '/admin/system-settings',
            '/admin/user-profiles',
            '/admin/users',
        ];

        foreach ($routes as $uri) {
            $response = $this->get($uri);
            $this->assertSame(
                200,
                $response->status(),
                "Expected 200 for GET {$uri}, got {$response->status()}"
            );
        }
    }

    public function test_filament_pages_resources_and_livewire_components_render(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $components = [
            Dashboard::class,
            AttendanceRecap::class,
            SystemSettingsPage::class,
            PhotoStamp::class,
            Leaderboard::class,
            MySurveys::class,
            CreateBulkSuratTugas::class,
            ManageBlockedNumbers::class,
            ListDailyActivities::class,
            CreateDailyActivity::class,
            ListSuratTugas::class,
            CreateSuratTugas::class,
            ListOffices::class,
            CreateOffice::class,
            ListPelatihans::class,
            CreatePelatihan::class,
            ListLaporanPerjalananDinas::class,
            CreateLaporanPerjalananDinas::class,
            ListLaporanLemburs::class,
            CreateLaporanLembur::class,
            ListRoles::class,
            CreateUser::class,
            Presensi::class,
            AttendanceUnattendedRecap::class,
        ];

        foreach ($components as $component) {
            try {
                Livewire::test($component)->assertSuccessful();
            } catch (\Throwable $e) {
                $this->fail("Livewire component {$component} failed: {$e->getMessage()}");
            }
        }
    }

    public function test_filament_widgets_render_via_livewire(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $widgets = [
            CalendarWidget::class,
            DailyActivityCalendarWidget::class,
            StatsOverviewWidget::class,
            TodayAttendanceStats::class,
            TodayPresencePie::class,
            LatestSuratTugasWidget::class,
            SuratTugasChartWidget::class,
            SurveyChartWidget::class,
            AttendanceLast7DaysChart::class,
        ];

        foreach ($widgets as $widget) {
            try {
                Livewire::test($widget)->assertSuccessful();
            } catch (\Throwable $e) {
                $this->fail("Livewire widget {$widget} failed: {$e->getMessage()}");
            }
        }
    }

    public function test_generate_sppd_table_action_validates_nomor_sppd_cleanly(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $survey = Survey::create([
            'name' => 'Survei Test SPPD',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        $suratTugas = SuratTugas::create([
            'user_id' => $this->superAdmin->id,
            'survey_id' => $survey->id,
            'nomor_surat' => 'B-0001/33210/KP.650/2026',
            'nomor_urut' => 1,
            'kode_klasifikasi' => 'KP.650',
            'jabatan' => 'Statistisi',
            'keperluan' => 'Pengawasan Lapangan',
            'tempat_tugas' => 'Demak',
            'tanggal' => now()->toDateString(),
            'waktu_mulai' => now(),
            'waktu_selesai' => now()->addHours(4),
            'signer_name' => 'Kepala BPS',
            'signer_nip' => '198001012005011001',
            'signer_title' => 'Kepala',
            'signer_city' => 'Demak',
            'status' => 'approved',
        ]);

        Livewire::test(ListSuratTugas::class)
            ->callTableAction('generate_sppd', $suratTugas, data: [
                'nomor_sppd' => 'B-1192/33210/SE2026/KP.650/2026',
                'nomor_urut_sppd' => 1192,
                'kode_klasifikasi_sppd' => 'KP.650',
                'tahun_sppd' => 2026,
                'tingkat_perjalanan_dinas' => 'C',
                'alat_angkutan' => 'Kendaraan Pribadi',
                'mak' => '054.01.GG.2902.006.005.A.524113',
                'biaya_transport' => 170000,
                'maksud_perjalanan' => 'Transport lokal dalam rangka Pengawasan Lapangan',
                'tempat_berangkat' => 'Demak',
                'tempat_tujuan' => 'Demak',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('sppds', [
            'surat_tugas_id' => $suratTugas->id,
            'nomor_sppd' => 'B-1192/33210/SE2026/KP.650/2026',
        ]);
    }
}
