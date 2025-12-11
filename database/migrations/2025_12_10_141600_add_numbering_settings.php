<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.office_code', '33210');
        $this->migrator->add('system.surat_prefix', 'B');
    }
};
