<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.kegiatan_roles', [
            'PML',
            'PCL',
            'Penanggung Jawab',
            'Panitia',
            'Peserta',
        ]);
        $this->migrator->add('system.kegiatan_roles_map', [
            'PML' => 'Petugas Pengawas Lapangan',
            'PCL' => 'Petugas Pencacah Lapangan',
            'Penanggung Jawab' => 'Penanggung Jawab',
            'Panitia' => 'Panitia',
            'Peserta' => 'Peserta',
        ]);
    }
};
