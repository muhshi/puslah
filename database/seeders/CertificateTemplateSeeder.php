<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CertificateTemplate::firstOrCreate(
            ['code' => 'A4-LANDSCAPE-01'],
            [
                'name' => 'Template Biru Minimalis',
                'background_path' => 'cert_templates/template1.png', // upload dulu ke storage/app/public
                'paper' => 'a4',
                'orientation' => 'landscape',
                'active' => true,
            ]
        );
    }
}
