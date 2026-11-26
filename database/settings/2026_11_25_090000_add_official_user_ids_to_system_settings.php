<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.kepala_user_id', null);
        $this->migrator->add('system.ppk_user_id', null);
        $this->migrator->add('system.bendahara_user_id', null);
        $this->migrator->add('system.bendahara_name', '-');
        $this->migrator->add('system.bendahara_nip', '-');
        $this->migrator->add('system.bendahara_title', 'Bendahara Pengeluaran');
        $this->migrator->add('system.undangan_pelatihan_template_path', null);
    }
};
