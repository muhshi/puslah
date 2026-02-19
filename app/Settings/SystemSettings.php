<?php
namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SystemSettings extends Settings
{
    public float $default_office_lat;
    public float $default_office_lng;
    public int $default_geofence_radius_m;
    public string $default_work_start; // 'H:i'
    public string $default_work_end;   // 'H:i'
    /** @var array<int> 1=Mon ... 7=Sun */
    public array $default_workdays;
    public string $default_office_name;
    public string $cert_signer_name;
    public string $cert_signer_nip;  // Added NIP
    public string $cert_signer_title; // Jabatan
    public string $cert_city;         // Kota penetapan surat
    public string $office_code;       // Kode Kantor (ex: 33210)
    public string $surat_prefix;      // Prefix Surat (ex: B)
    public ?string $surat_tugas_template_path; // Path to DOCX template
    public ?string $laporan_dinas_template_path; // Path to Laporan DOCX template
    public ?string $surat_pernyataan_template_path; // Path to Surat Pernyataan DOCX template
    public ?string $cert_signer_signature_path;
    public string $cert_number_prefix;        // ex: 'BPS-DMK'
    public array $cert_number_seq_by_year;    // ex: ['2025'=>123]
    public ?string $logo_bps_path;
    public ?string $pdf_master_password;

    public static function defaults(): array
    {
        return [
            'default_office_lat' => 0.0,
            'default_office_lng' => 0.0,
            'default_geofence_radius_m' => 100,
            'default_work_start' => '08:00',
            'default_work_end' => '16:00',
            'default_workdays' => [1, 2, 3, 4, 5],
            'default_office_name' => 'BPS Kabupaten Demak',
            'cert_city' => 'Demak',
            'cert_signer_name' => '-',
            'cert_signer_nip' => '-',
            'cert_signer_title' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
            'office_code' => '33210',
            'surat_prefix' => 'B',
            'surat_tugas_template_path' => null,
            'laporan_dinas_template_path' => null,
            'surat_pernyataan_template_path' => null,
            'cert_signer_signature_path' => null,
            'cert_number_prefix' => 'BPS-DMK',
            'cert_number_seq_by_year' => [],
            'logo_bps_path' => null,
            'pdf_master_password' => null,
        ];
    }

    public static function group(): string
    {
        return 'system';
    }
}
