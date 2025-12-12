<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.surat_pernyataan_template_path', null);
    }

    public function down(): void
    {
        $this->migrator->delete('system.surat_pernyataan_template_path');
    }
};
