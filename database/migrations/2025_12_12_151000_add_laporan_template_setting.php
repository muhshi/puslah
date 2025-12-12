<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.laporan_dinas_template_path', null);
    }

    public function down(): void
    {
        $this->migrator->delete('system.laporan_dinas_template_path');
    }
};
