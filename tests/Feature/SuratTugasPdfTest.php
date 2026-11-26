<?php

namespace Tests\Feature;

use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\User;
use App\Services\SuratTugasPdfService;
use App\Settings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuratTugasPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSettings::fake(SystemSettings::defaults());
        Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
    }

    public function test_dompdf_allows_data_uri_protocol_for_inline_images_and_qr(): void
    {
        $protocols = config('dompdf.options.allowed_protocols');

        $this->assertIsArray($protocols);
        $this->assertArrayHasKey('data://', $protocols);
    }

    public function test_approved_surat_tugas_pdf_renders_logo_and_qr_without_dompdf_warnings(): void
    {
        global $_dompdf_warnings;
        $_dompdf_warnings = [];

        $user = User::factory()->create(['name' => 'Pegawai Uji']);
        $user->profile->update([
            'full_name' => 'Pegawai Uji, S.Stat',
            'jabatan' => 'Statistisi Ahli Pertama',
        ]);

        $survey = Survey::create([
            'name' => 'Survei Uji Coba',
            'dasar_surat' => 'DIPA BPS Kabupaten Demak Tahun 2026',
            'start_date' => '2026-11-26',
            'end_date' => '2026-11-27',
            'is_active' => true,
        ]);

        $suratTugas = SuratTugas::create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
            'nomor_surat' => 'B-0001/33210/KP.650/2026',
            'nomor_urut' => 1,
            'kode_klasifikasi' => 'KP.650',
            'jabatan' => 'Petugas Pendataan Lapangan',
            'tanggal' => '2026-11-26',
            'waktu_mulai' => '2026-11-26',
            'waktu_selesai' => '2026-11-27',
            'keperluan' => 'Pelaksanaan Survei Uji Coba',
            'tempat_tugas' => 'Kabupaten Demak',
            'signer_name' => 'Khomarudin',
            'signer_nip' => '197512091999011001',
            'signer_title' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
            'signer_city' => 'Demak',
            'status' => 'approved',
            'hash' => 'testhash1234567890abcdefghijklmn',
        ]);

        $pdf = app(SuratTugasPdfService::class)->renderPdf($suratTugas);
        $output = $pdf->output();
        $warnings = implode("\n", $_dompdf_warnings ?? []);

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertDoesNotMatchRegularExpression('/Permission denied|protocol is not supported|Image not found/i', $warnings);
        $this->assertStringContainsString('/Subtype /Image', $output);
        $this->assertGreaterThan(0, $pdf->getDomPDF()->getCanvas()->get_cpdf()->numImages);
        $this->assertGreaterThan(15000, strlen($output));
    }
}
