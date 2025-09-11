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
    public string $cert_signer_title;
    public ?string $cert_signer_signature_path;
    public string $cert_number_prefix;        // ex: 'BPS-DMK'
    public array $cert_number_seq_by_year;    // ex: ['2025'=>123]

    public static function defaults(): array
    {
        return [
            'default_office_lat' => 0.0,
            'default_office_lng' => 0.0,
            'default_geofence_radius_m' => 100,
            'default_work_start' => '08:00',
            'default_work_end' => '16:00',
            'default_workdays' => [1, 2, 3, 4, 5],
            'default_office_name' => 'Puslah BPS Demak',
        ];
    }

    public static function group(): string
    {
        return 'system';
    }
}
