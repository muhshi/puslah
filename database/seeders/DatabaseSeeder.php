<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Roles
        $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
        $mitraRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Mitra']);
        $organikRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Organik']);
        $pengolahanRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Pengolahan']);
        $kepalaRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Kepala']);
        $operatorRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Operator']);
        $ketuaTimRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Ketua Tim']);

        // Generate Permissions via Shield
        \Illuminate\Support\Facades\Artisan::call('shield:generate', ['--all' => true]);

        // Assign Permissions based on CSV
        $rolePermissions = [
            'super_admin' => [
                'view_attendance',
                'view_any_attendance',
                'create_attendance',
                'update_attendance',
                'restore_attendance',
                'restore_any_attendance',
                'delete_attendance',
                'delete_any_attendance',
                'view_attendance::rule',
                'view_any_attendance::rule',
                'create_attendance::rule',
                'update_attendance::rule',
                'restore_attendance::rule',
                'restore_any_attendance::rule',
                'delete_attendance::rule',
                'delete_any_attendance::rule',
                'view_certificate::template',
                'view_any_certificate::template',
                'create_certificate::template',
                'update_certificate::template',
                'restore_certificate::template',
                'restore_any_certificate::template',
                'delete_certificate::template',
                'delete_any_certificate::template',
                'view_laporan::perjalanan::dinas',
                'view_any_laporan::perjalanan::dinas',
                'create_laporan::perjalanan::dinas',
                'update_laporan::perjalanan::dinas',
                'restore_laporan::perjalanan::dinas',
                'restore_any_laporan::perjalanan::dinas',
                'delete_laporan::perjalanan::dinas',
                'delete_any_laporan::perjalanan::dinas',
                'view_leave',
                'view_any_leave',
                'create_leave',
                'update_leave',
                'restore_leave',
                'restore_any_leave',
                'delete_leave',
                'delete_any_leave',
                'view_office',
                'view_any_office',
                'create_office',
                'update_office',
                'restore_office',
                'restore_any_office',
                'delete_office',
                'delete_any_office',
                'view_schedule',
                'view_any_schedule',
                'create_schedule',
                'update_schedule',
                'restore_schedule',
                'restore_any_schedule',
                'delete_schedule',
                'delete_any_schedule',
                'view_shield::role',
                'view_any_shield::role',
                'create_shield::role',
                'update_shield::role',
                'delete_shield::role',
                'delete_any_shield::role',
                'view_shift',
                'view_any_shift',
                'create_shift',
                'update_shift',
                'restore_shift',
                'restore_any_shift',
                'delete_shift',
                'delete_any_shift',
                'view_surat::tugas',
                'view_any_surat::tugas',
                'create_surat::tugas',
                'update_surat::tugas',
                'restore_surat::tugas',
                'restore_any_surat::tugas',
                'delete_surat::tugas',
                'delete_any_surat::tugas',
                'view_survey',
                'view_any_survey',
                'create_survey',
                'update_survey',
                'restore_survey',
                'restore_any_survey',
                'delete_survey',
                'delete_any_survey',
                'view_survey::user',
                'view_any_survey::user',
                'create_survey::user',
                'update_survey::user',
                'restore_survey::user',
                'restore_any_survey::user',
                'delete_survey::user',
                'delete_any_survey::user',
                'view_user',
                'view_any_user',
                'create_user',
                'update_user',
                'restore_user',
                'restore_any_user',
                'delete_user',
                'delete_any_user',
                'view_user::profile',
                'view_any_user::profile',
                'create_user::profile',
                'update_user::profile',
                'restore_user::profile',
                'restore_any_user::profile',
                'delete_user::profile',
                'delete_any_user::profile',
                'page_AttendanceRecap',
                'page_MySurveys',
                'page_ProfileMe',
                'page_SystemSettingsPage',
                'widget_AttendanceLast7DaysChart',
                'widget_StatsOverviewWidget',
                'widget_TodayAttendanceStats',
                'widget_TodayPresencePie',
                'widget_SuratTugasChartWidget',
                'widget_LatestSuratTugasWidget',
            ],
            'Mitra' => [
                'view_laporan::perjalanan::dinas',
                'view_any_laporan::perjalanan::dinas',
                'create_laporan::perjalanan::dinas',
                'update_laporan::perjalanan::dinas',
                'restore_laporan::perjalanan::dinas',
                'restore_any_laporan::perjalanan::dinas',
                'delete_laporan::perjalanan::dinas',
                'delete_any_laporan::perjalanan::dinas',
                'view_surat::tugas',
                'view_any_surat::tugas',
                'page_MySurveys',
                'widget_SuratTugasChartWidget',
            ],
            'Organik' => [
                'view_laporan::perjalanan::dinas',
                'view_any_laporan::perjalanan::dinas',
                'create_laporan::perjalanan::dinas',
                'update_laporan::perjalanan::dinas',
                'restore_laporan::perjalanan::dinas',
                'restore_any_laporan::perjalanan::dinas',
                'delete_laporan::perjalanan::dinas',
                'delete_any_laporan::perjalanan::dinas',
                'view_surat::tugas',
                'view_any_surat::tugas',
                'page_MySurveys',
                'page_ProfileMe',
                'widget_SuratTugasChartWidget',
            ],
            'Operator' => [
                'view_surat::tugas',
                'view_any_surat::tugas',
                'create_surat::tugas',
                'update_surat::tugas',
                'restore_surat::tugas',
                'restore_any_surat::tugas',
                'delete_surat::tugas',
                'delete_any_surat::tugas',
                'view_survey',
                'view_any_survey',
                'create_survey',
                'update_survey',
                'restore_survey',
                'restore_any_survey',
                'delete_survey',
                'delete_any_survey',
                'view_survey::user',
                'view_any_survey::user',
            ],
            'Ketua Tim' => [
                'view_surat::tugas',
                'view_any_surat::tugas',
                'create_surat::tugas',
                'update_surat::tugas',
                'restore_surat::tugas',
                'restore_any_surat::tugas',
                'delete_surat::tugas',
                'delete_any_surat::tugas',
                'view_survey',
                'view_any_survey',
                'create_survey',
                'update_survey',
                'restore_survey',
                'restore_any_survey',
                'delete_survey',
                'delete_any_survey',
                'view_survey::user',
                'view_any_survey::user',
            ],
            'Pengolahan' => [
                'view_attendance',
                'view_any_attendance',
                'view_leave',
                'view_any_leave',
                'create_leave',
                'update_leave',
                'restore_leave',
                'restore_any_leave',
                'delete_leave',
                'delete_any_leave',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
            if ($role) {
                // Determine guard, default to web. Shield generates for web usually.
                $role->syncPermissions($permissions);
            }
        }

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin'),
        ]);
        $admin->removeRole('Mitra');
        $admin->assignRole('super_admin');


        User::factory()->create([
            'name' => 'Muhshi',
            'email' => 'amuhshi@gmail.com',
            'password' => bcrypt('muhshi'),
        ]);
        User::factory()->create([
            'name' => 'Masykuri Zaen',
            'email' => 'zaen@gmail.com',
            'password' => bcrypt('zaen'),
        ]);

        $this->call(OfficeSeeder::class);
        $this->call(ShiftSeeder::class);

    }
}
