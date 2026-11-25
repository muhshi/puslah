<?php

namespace Tests\Feature;

use App\Filament\Pages\AttendanceRecap;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PhotoStamp;
use App\Filament\Pages\SystemSettingsPage;
use App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities;
use App\Filament\Resources\LaporanLemburResource\Pages\ListLaporanLemburs;
use App\Filament\Resources\LaporanPerjalananDinasResource\Pages\ListLaporanPerjalananDinas;
use App\Filament\Resources\OfficeResource\Pages\ListOffices;
use App\Filament\Resources\Shield\RoleResource\Pages\ListRoles;
use App\Filament\Resources\SuratTugasResource\Pages\ListSuratTugas;
use App\Livewire\AttendanceUnattendedRecap;
use App\Livewire\Presensi;
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

    public function test_guest_can_access_admin_login_page(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_super_admin_can_get_admin_panel_and_main_routes(): void
    {
        $this->actingAs($this->superAdmin);

        $routes = [
            '/admin',
            '/admin/attendance-recap',
            '/admin/attendance-rules',
            '/admin/attendances',
            '/admin/certificate-templates',
            '/admin/daily-activities',
            '/admin/laporan-lemburs',
            '/admin/laporan-perjalanan-dinas',
            '/admin/leaderboard',
            '/admin/leaves',
            '/admin/my-surveys',
            '/admin/offices',
            '/admin/schedules',
            '/admin/shared-work-reports',
            '/admin/shield/roles',
            '/admin/shifts',
            '/admin/stempel-foto',
            '/admin/surat-tugas',
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

    public function test_filament_pages_and_resources_render_via_livewire(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $components = [
            Dashboard::class,
            AttendanceRecap::class,
            SystemSettingsPage::class,
            PhotoStamp::class,
            ListDailyActivities::class,
            ListSuratTugas::class,
            ListOffices::class,
            ListLaporanPerjalananDinas::class,
            ListLaporanLemburs::class,
            ListRoles::class,
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
}
