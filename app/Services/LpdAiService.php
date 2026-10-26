<?php

namespace App\Services;

use App\Models\LaporanPerjalananDinas;
use App\Models\SuratTugas;
use App\Models\User;
use App\Settings\SystemSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LpdAiService
{
    /**
     * Dapatkan API Key Gemini dari Pengaturan Sistem atau file .env.
     */
    public function getApiKey(): ?string
    {
        $settingsKey = app(SystemSettings::class)->gemini_api_key ?? null;
        if (!empty($settingsKey)) {
            return trim($settingsKey);
        }

        $configKey = config('services.gemini.api_key');
        if (!empty($configKey)) {
            return trim($configKey);
        }

        return env('GEMINI_API_KEY');
    }

    /**
     * Dapatkan model Gemini yang dipilih.
     */
    public function getModel(): string
    {
        $settingsModel = app(SystemSettings::class)->gemini_model ?? null;
        if (!empty($settingsModel)) {
            return trim($settingsModel);
        }

        return config('services.gemini.model', 'gemini-3.5-flash');
    }

    /**
     * Mengambil 2-3 contoh LPD nyata dari database untuk Few-Shot In-Context Learning.
     * Mengutamakan LPD dengan survei/kegiatan yang sejenis.
     *
     * @param int|null $surveyId
     * @return array<int, array{tujuan: string, uraian: string, pejabat: string, desa: string}>
     */
    public function getFewShotExamples(?int $surveyId = null): array
    {
        $query = LaporanPerjalananDinas::query()
            ->whereNotNull('uraian_kegiatan')
            ->whereRaw('LENGTH(uraian_kegiatan) > 100')
            ->with(['suratTugas.survey']);

        if ($surveyId) {
            $query->orderByRaw("CASE WHEN surat_tugas_id IN (SELECT id FROM surat_tugas WHERE survey_id = ?) THEN 0 ELSE 1 END", [$surveyId]);
        }

        $records = $query->latest()->take(3)->get();

        $examples = [];
        foreach ($records as $rec) {
            $surveyName = $rec->suratTugas?->survey?->name ?? 'Survei Lapangan';
            $tujuan = $rec->tujuan ?: $rec->suratTugas?->keperluan ?: '-';
            $uraian = strip_tags($rec->uraian_kegiatan);
            // Batasi panjang jika terlalu panjang agar hemat token
            if (mb_strlen($uraian) > 600) {
                $uraian = mb_substr($uraian, 0, 600) . '...';
            }

            $examples[] = [
                'survei' => $surveyName,
                'tujuan' => $tujuan,
                'pejabat' => $rec->nama_pejabat ?: '-',
                'desa' => $rec->desa_pejabat ?: '-',
                'uraian' => $uraian,
            ];
        }

        return $examples;
    }

    /**
     * Generate narasi laporan perjalanan dinas berbasis Gemini AI.
     *
     * @param array{
     *     surat_tugas_id?: int|string|null,
     *     tujuan?: string|null,
     *     tanggal_kunjungan?: string|null,
     *     nama_pejabat?: string|null,
     *     desa_pejabat?: string|null,
     *     user_id?: int|string|null,
     *     catatan: string,
     *     gaya?: string
     * } $params
     * @return array{narasi: string, nama_pejabat: ?string, desa_pejabat: ?string}
     * @throws Exception
     */
    public function generateNarasi(array $params): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception("API Key Gemini belum disetel. Silakan isi di Pengaturan Sistem (tab Integrasi AI) atau tambahkan GEMINI_API_KEY di file .env.");
        }

        $model = $this->getModel();

        // 1. Kumpulkan metadata konteks
        $suratTugas = null;
        if (!empty($params['surat_tugas_id'])) {
            $suratTugas = SuratTugas::with(['survey', 'user'])->find($params['surat_tugas_id']);
        }

        $surveyName = $suratTugas?->survey?->name ?? 'Kegiatan BPS';
        $tujuan = $params['tujuan'] ?? $suratTugas?->keperluan ?? 'Tugas Lapangan BPS';
        $tanggal = $params['tanggal_kunjungan'] ?? null;
        $tanggalFormatted = $tanggal ? \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') : 'hari tugas';

        $userId = $params['user_id'] ?? $suratTugas?->user_id ?? Auth::id();
        $user = $userId ? User::find($userId) : Auth::user();
        $petugasName = $user?->name ?? 'Petugas Lapangan';

        $namaPejabat = $params['nama_pejabat'] ?? null;
        $desaPejabat = $params['desa_pejabat'] ?? null;
        $catatanKasar = trim($params['catatan']);
        $gaya = $params['gaya'] ?? 'paragraf';

        // 2. Ambil contoh LPD nyata dari DB
        $surveyId = $suratTugas?->survey_id;
        $examples = $this->getFewShotExamples($surveyId);

        $examplesPrompt = "";
        if (!empty($examples)) {
            $examplesPrompt .= "Berikut adalah contoh gaya penulisan LPD nyata pegawai BPS Kabupaten Demak yang sudah ada di database:\n\n";
            foreach ($examples as $idx => $ex) {
                $no = $idx + 1;
                $examplesPrompt .= "--- CONTOH {$no} ---\n";
                $examplesPrompt .= "Survei: {$ex['survei']}\n";
                $examplesPrompt .= "Tujuan: {$ex['tujuan']}\n";
                $examplesPrompt .= "Pejabat/Lokasi: {$ex['pejabat']} ({$ex['desa']})\n";
                $examplesPrompt .= "Uraian Narasi:\n{$ex['uraian']}\n\n";
            }
        }

        // 3. Susun System & User Prompt
        $prompt = <<<EOT
Anda adalah asisten AI resmi Badan Pusat Statistik (BPS) Kabupaten Demak.
Tugas Anda adalah menyusun narasi 'Uraian Kegiatan' untuk Laporan Perjalanan Dinas (LPD) yang formal, akuntabel, runtut kronologis, dan profesional berdasarkan catatan singkat dari pegawai.

{$examplesPrompt}

INFORMASI TUGAS YANG SEDANG DILAKSANAKAN:
- Nama Petugas/Pegawai: {$petugasName}
- Survei / Kegiatan: {$surveyName}
- Tujuan / Keperluan Surat Tugas: {$tujuan}
- Tanggal Kunjungan: {$tanggalFormatted}
- Nama Pejabat yang Dikunjungi (jika sudah ada): {$namaPejabat}
- Desa/Lokasi Pejabat (jika sudah ada): {$desaPejabat}
- Gaya Penulisan yang Diminta: {$gaya}

CATATAN SINGKAT DARI PEGAWAI (KATA KUNCI / DRAFT LAPANGAN):
"{$catatanKasar}"

PETUNJUK FORMAT & GAYA BAHASA (MUTLAK):
1. JANGAN PERNAH MENGGUNAKAN NOMOR ATAU ANGKA PENOMORAN (1, 2, 3, DST MAUPUN TAG <ol><li>)! Pengguna TIDAK INGIN format nomor.
2. Format output HARUS berupa 2 sampai 3 paragraf narasi mengalir yang utuh dan rapi menggunakan tag <p>.
3. RANGKAI ESTIMASI JAM SECARA REALISTIS DAN LOGIS DARI PAGI SAMPAI PULANG:
   - Sebutkan jam keberangkatan dari Kantor BPS Kabupaten Demak (pukul 08.00 WIB) dan jam tiba di lokasi/desa sasaran.
   - Sebutkan jam koordinasi dengan aparat desa setempat, jam briefing lapangan, dan jam pelaksanaan wawancara/pemeriksaan responden pagi hari.
   - WAJIB sertakan waktu istirahat, salat, dan makan siang (ISHOMA) sekitar pukul 12.00–13.00 WIB.
   - Sebutkan kelanjutan kegiatan siang hari (pukul 13.00–15.30 WIB).
   - WAJIB sebutkan jam saat seluruh rangkaian kegiatan lapangan selesai dan petugas memulai perjalanan pulang menuju Kantor BPS Kabupaten Demak (sekitar pukul 15.30 WIB).
   - WAJIB sebutkan jam saat tiba kembali di Kantor BPS Kabupaten Demak (sekitar pukul 16.30 WIB) dan mengakhiri pelaksanaan tugas perjalanan dinas.
4. Bahasa: Bahasa Indonesia resmi kedinasan BPS yang formal, tertib, dan akuntabel.
5. Istilah BPS: Pertahankan dan gunakan secara tepat istilah teknis survei statistik bila relevan (misal: responden, sampel, komoditas, PPL, PML, Koseka, Task Force, INNAS, Fasih, CAPI, PAPI, blok sensus, dll.).
6. Deteksi Pejabat & Desa:
   - Jika pada catatan pegawai terdeteksi nama pejabat/tokoh (misal kades, sekdes, ketua RT/RW, responden utama), ekstrak ke field "nama_pejabat" beserta jabatannya (misal: "Bapak Mufna'am (Sekretaris Desa Morodemak)").
   - Jika terdeteksi nama desa/kecamatan/lokasi, ekstrak ke field "desa_pejabat" (misal: "Desa Morodemak").

HASIL HARUS DALAM FORMAT JSON BERSIH BERIKUT (tanpa markdown backticks):
{
  "narasi": "<p>Teks paragraf narasi di sini...</p>",
  "nama_pejabat": "Nama pejabat terdeteksi atau null jika tidak ada",
  "desa_pejabat": "Desa/lokasi terdeteksi atau null jika tidak ada"
}
EOT;

        // 4. Panggil Gemini API dengan automatic fallback jika model 404/503
        $model = $this->getModel();
        $modelsToTry = array_unique([$model, 'gemini-3.1-flash-lite', 'gemini-3.5-flash', 'gemini-3.8-flash']);

        $response = null;
        $lastError = null;

        foreach ($modelsToTry as $currentModel) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$currentModel}:generateContent?key={$apiKey}";

            try {
                $res = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(25)->post($endpoint, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.3,
                    ],
                ]);

                if ($res->successful()) {
                    $response = $res;
                    break;
                }

                $status = $res->status();
                $errBody = $res->json('error.message') ?? $res->body();
                $lastError = "Model {$currentModel} (HTTP {$status}): {$errBody}";
                Log::warning("Gemini model {$currentModel} gagal, mencoba model alternatif...", ['error' => $lastError]);
            } catch (\Exception $e) {
                $lastError = "Model {$currentModel}: " . $e->getMessage();
                Log::warning("Gemini model {$currentModel} exception, mencoba model alternatif...", ['error' => $lastError]);
            }
        }

        if (!$response || !$response->successful()) {
            throw new Exception("Gemini API Error: " . ($lastError ?: "Gagal menghubungi Gemini API. Silakan periksa koneksi internet atau API key."));
        }

        $rawText = $response->json('candidates.0.content.parts.0.text');
        if (empty($rawText)) {
            throw new Exception("Gemini API tidak memberikan respon teks.");
        }

        // Parse JSON
        $decoded = json_decode($rawText, true);
        if (!is_array($decoded) || !isset($decoded['narasi'])) {
            // Bersihkan jika ada markdown code block ```json ... ```
            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
            $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
            $decoded = json_decode($cleaned, true);
        }

        if (is_array($decoded) && isset($decoded['narasi'])) {
            return [
                'narasi' => $decoded['narasi'],
                'nama_pejabat' => $decoded['nama_pejabat'] ?? null,
                'desa_pejabat' => $decoded['desa_pejabat'] ?? null,
            ];
        }

        // Fallback jika tidak berhasil parse JSON
        return [
            'narasi' => '<p>' . nl2br(htmlspecialchars($rawText)) . '</p>',
            'nama_pejabat' => null,
            'desa_pejabat' => null,
        ];
    }
}
