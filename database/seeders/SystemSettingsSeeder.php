<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Settings\SystemSettings;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SystemSettings::class)->fill([
            'default_office_lat' => -6.893,       // contoh
            'default_office_lng' => 110.639,      // contoh
            'default_geofence_radius_m' => 100,
            'default_work_start' => '08:00',
            'default_work_end' => '16:00',
            'default_workdays' => [1, 2, 3, 4, 5],  // Sen–Jum
        ])->save();
    }
}
