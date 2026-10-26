<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.gemini_api_key', null);
        $this->migrator->add('system.gemini_model', 'gemini-1.5-flash');
    }
};
