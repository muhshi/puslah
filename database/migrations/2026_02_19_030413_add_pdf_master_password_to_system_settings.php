<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.pdf_master_password', null);
    }

    public function down(): void
    {
        $this->migrator->delete('system.pdf_master_password');
    }
};
