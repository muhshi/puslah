<?php

namespace Database\Seeders;

use App\Models\KopSurat;
use Illuminate\Database\Seeder;

class KopSuratSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTeks = "Jl. Sultan Hadiwijaya No. 23 Demak Telp/Fax : (0291) 685445\nHomepage: http://demakkab.bps.go.id, E-mail : bps3321@bps.go.id";

        // 1. Kop BerAKHLAK (Default)
        KopSurat::firstOrCreate(
            ['name' => 'Kop BerAKHLAK (Default)'],
            [
                'logo_kiri_path'  => 'images/kop/logo_bps_header.png',
                'logo_kanan_path' => 'images/kop/logo_berakhlak.png',
                'instansi_teks'   => $defaultTeks,
                'is_active'       => true,
                'is_default'      => true,
            ]
        );

        // 2. Kop Sensus Ekonomi 2026 (SE2026)
        KopSurat::firstOrCreate(
            ['name' => 'Kop Sensus Ekonomi 2026 (SE2026)'],
            [
                'logo_kiri_path'  => 'images/kop/logo_bps_header.png',
                'logo_kanan_path' => 'images/kop/logo_se26.png',
                'instansi_teks'   => $defaultTeks,
                'is_active'       => true,
                'is_default'      => false,
            ]
        );
    }
}
