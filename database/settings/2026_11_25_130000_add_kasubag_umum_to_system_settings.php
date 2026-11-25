<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.kasubag_umum_user_id', null);
        $this->migrator->add('system.kasubag_umum_name', '-');
        $this->migrator->add('system.kasubag_umum_nip', '-');
        $this->migrator->add('system.kasubag_umum_title', 'Kepala Subbagian Umum');
    }
};
