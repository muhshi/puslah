<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add('system.cert_signer_name', 'Kepala BPS Kab. Demak');
        $this->migrator->add('system.cert_signer_title', 'Kepala');
        $this->migrator->add('system.cert_signer_signature_path', null);
        $this->migrator->add('system.cert_number_prefix', 'BPS-DMK');
        $this->migrator->add('system.cert_number_seq_by_year', []);
    }
};
