<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.sppd_template_path', null);
        $this->migrator->add('system.ppk_name', '-');
        $this->migrator->add('system.ppk_nip', '-');
        $this->migrator->add('system.ppk_title', 'Pejabat Pembuat Komitmen');
    }
};
