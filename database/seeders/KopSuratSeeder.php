<?php

namespace Database\Seeders;

use App\Models\KopSurat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class KopSuratSeeder extends Seeder
{
    public function run(): void
    {
        // Salin template docx bawaan ke storage public agar konsisten dengan file upload
        if (file_exists(base_path('kop berakhlak.docx'))) {
            Storage::disk('public')->put(
                'kop-surat/kop_berakhlak.docx',
                file_get_contents(base_path('kop berakhlak.docx'))
            );
        }
        if (file_exists(base_path('kop se26.docx'))) {
            Storage::disk('public')->put(
                'kop-surat/kop_se26.docx',
                file_get_contents(base_path('kop se26.docx'))
            );
        }

        // 1. Kop BerAKHLAK (Default)
        KopSurat::updateOrCreate(
            ['name' => 'Kop BerAKHLAK (Default)'],
            [
                'template_docx_path' => 'kop-surat/kop_berakhlak.docx',
                'logo_kiri_path'     => 'images/kop/logo_bps_header.png',
                'logo_kanan_path'    => 'images/kop/logo_berakhlak.png',
                'instansi_teks'      => null,
                'is_active'          => true,
                'is_default'         => true,
            ]
        );

        // 2. Kop SE2026
        KopSurat::updateOrCreate(
            ['name' => 'Kop SE2026'],
            [
                'template_docx_path' => 'kop-surat/kop_se26.docx',
                'logo_kiri_path'     => 'images/kop/logo_bps_header.png',
                'logo_kanan_path'    => 'images/kop/logo_se26.png',
                'instansi_teks'      => null,
                'is_active'          => true,
                'is_default'         => false,
            ]
        );
    }
}
