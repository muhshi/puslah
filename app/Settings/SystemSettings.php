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
    public ?string $laporan_lembur_template_path; // Path to Laporan Lembur DOCX template
    public ?string $cert_signer_signature_path;
    public string $cert_number_prefix;        // ex: 'BPS-DMK'
    public array $cert_number_seq_by_year;    // ex: ['2025'=>123]
    public ?string $logo_bps_path;
    public ?string $pdf_master_password;
    
    // SPPD Settings
    public ?string $sppd_template_path;
    public string $ppk_name;
    public string $ppk_nip;
    public string $ppk_title;

    // AI Settings
    public ?string $gemini_api_key;
    public ?string $gemini_model;

    // Kegiatan Settings
    public array $kegiatan_roles;
    public array $kegiatan_roles_map;

    public function getCertificateRole(?string $role): string
    {
        if (!$role) {
            return 'Petugas';
        }

        $map = $this->kegiatan_roles_map ?? [];
        if (isset($map[$role])) {
            return $map[$role];
        }

        foreach ($map as $key => $fullName) {
            if (strcasecmp($key, $role) === 0) {
                return $fullName;
            }
        }

        // Fallback default BPS standard abbreviations
        $upper = strtoupper(trim($role));
        if ($upper === 'PML') {
            return 'Petugas Pengawas Lapangan';
        }
        if ($upper === 'PCL') {
            return 'Petugas Pencacah Lapangan';
        }

        return $role;
    }

    public function getRoleOptions(): array
    {
        $map = $this->kegiatan_roles_map ?? [
            'PML' => 'Petugas Pengawas Lapangan',
            'PCL' => 'Petugas Pencacah Lapangan',
            'Penanggung Jawab' => 'Penanggung Jawab',
            'Panitia' => 'Panitia',
            'Peserta' => 'Peserta',
        ];

        $options = [];
        foreach ($map as $code => $fullName) {
            if (strcasecmp($code, $fullName) === 0) {
                $options[$code] = $fullName;
            } else {
                $options[$code] = "{$code} ({$fullName})";
            }
        }

        return $options;
    }

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
            'laporan_lembur_template_path' => null,
            'cert_signer_signature_path' => null,
            'cert_number_prefix' => 'BPS-DMK',
            'cert_number_seq_by_year' => [],
            'logo_bps_path' => null,
            'pdf_master_password' => null,
            'sppd_template_path' => null,
            'ppk_name' => '-',
            'ppk_nip' => '-',
            'ppk_title' => 'Pejabat Pembuat Komitmen',
            'gemini_api_key' => null,
            'gemini_model' => 'gemini-3.5-flash',
            'kegiatan_roles' => ['PML', 'PCL', 'Penanggung Jawab', 'Panitia', 'Peserta'],
            'kegiatan_roles_map' => [
                'PML' => 'Petugas Pengawas Lapangan',
                'PCL' => 'Petugas Pencacah Lapangan',
                'Penanggung Jawab' => 'Penanggung Jawab',
                'Panitia' => 'Panitia',
                'Peserta' => 'Peserta',
            ],
        ];
    }

    public static function group(): string
    {
        return 'system';
    }
}
