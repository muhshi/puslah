<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.default_office_lat', 0.0);
        $this->migrator->add('system.default_office_lng', 0.0);
        $this->migrator->add('system.default_geofence_radius_m', 100);
        $this->migrator->add('system.default_work_start', '08:00');
        $this->migrator->add('system.default_work_end', '16:00');
        $this->migrator->add('system.default_workdays', [1, 2, 3, 4, 5]); // Sen–Jum
    }
};
