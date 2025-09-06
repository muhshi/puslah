<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.default_office_name', 'Kantor BPS Demak');
    }

    public function down(): void
    {
        $this->migrator->delete('system.default_office_name');
    }
};
