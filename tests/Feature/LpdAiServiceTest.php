<?php

namespace Tests\Feature;

use App\Models\LaporanPerjalananDinas;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\User;
use App\Services\LpdAiService;
use App\Settings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LpdAiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_it_throws_exception_if_no_api_key_configured(): void
    {
        SystemSettings::fake([
            'gemini_api_key' => null,
            'gemini_model' => 'gemini-1.5-flash',
        ]);
        config(['services.gemini.api_key' => null]);
        putenv('GEMINI_API_KEY');

        $service = new LpdAiService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Key Gemini belum disetel');

        $service->generateNarasi(['catatan' => 'testing tanpa key']);
    }

    public function test_it_generates_narasi_successfully_with_gemini_response(): void
    {
        SystemSettings::fake([
            'gemini_api_key' => 'test-gemini-key',
            'gemini_model' => 'gemini-3.5-flash',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'narasi' => '<p>Kegiatan pengawasan pendataan lapangan diawali pada pukul 08.30 WIB dengan berkoordinasi ke Desa Bandungrejo...</p>',
                                        'nama_pejabat' => 'Bapak Kepala Desa',
                                        'desa_pejabat' => 'Desa Bandungrejo',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new LpdAiService();
        $result = $service->generateNarasi([
            'catatan' => 'jam 08.30 koordinasi kades bandungrejo, jam 10 wawancara responden bu eka',
            'gaya' => 'paragraf',
        ]);

        $this->assertStringContainsString('Desa Bandungrejo', $result['narasi']);
        $this->assertEquals('Bapak Kepala Desa', $result['nama_pejabat']);
        $this->assertEquals('Desa Bandungrejo', $result['desa_pejabat']);
    }

    public function test_it_fetches_few_shot_examples_from_database(): void
    {
        SystemSettings::fake([
            'gemini_api_key' => 'test-gemini-key',
            'gemini_model' => 'gemini-3.5-flash',
        ]);

        $user = User::factory()->create();
        $survey = Survey::create([
            'name' => 'SITASI 2026',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'year' => 2026,
            'is_active' => true,
        ]);

        $st = SuratTugas::create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'nomor_surat' => 'B-0001/33210/KP.650/2026',
            'nomor_urut' => 1,
            'kode_klasifikasi' => 'KP.650',
            'jabatan' => 'Staff',
            'keperluan' => 'Pengawasan SITASI',
            'tanggal' => '2026-10-15',
            'waktu_mulai' => '2026-10-15 08:00:00',
            'waktu_selesai' => '2026-10-15 16:00:00',
            'status' => 'pending',
            'signer_name' => 'Kepala BPS',
            'signer_nip' => '198001012000011001',
            'signer_title' => 'Kepala BPS',
            'signer_city' => 'Demak',
        ]);

        LaporanPerjalananDinas::create([
            'surat_tugas_id' => $st->id,
            'nomor_surat_tugas' => $st->nomor_surat,
            'tujuan' => 'Pengawasan SITASI',
            'tanggal_kunjungan' => '2026-10-15',
            'uraian_kegiatan' => '<p>Kegiatan pengawasan pendataan lapangan diawali pada pukul 08.00 WIB menuju desa sampel untuk berkoordinasi dan supervisi responden secara teliti dan akuntabel.</p>',
            'nama_pejabat' => 'Bapak Kepala Desa',
            'desa_pejabat' => 'Desa Bandungrejo',
        ]);

        $service = new LpdAiService();
        $examples = $service->getFewShotExamples($survey->id);

        $this->assertNotEmpty($examples);
        $this->assertEquals('SITASI 2026', $examples[0]['survei']);
    }
}
