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
1. ATURAN UTAMA: PENGGUNA MEMINTA FORMAT "TETAP RAPI PER JAM TAPI TANPA BULLET MAUPUN NUMBERING".
2. DILARANG KERAS menggunakan tag <ul>, <ol>, <li>, simbol bullet (•, -, *), maupun angka penomoran (1., 2., 3., dst.).
3. Setiap tahapan kegiatan per jam HARUS dipisahkan menjadi paragraf <p> tersendiri.
4. Format setiap baris/paragraf WAJIB diawali jam tebal, contoh:
   <p><strong>Pukul HH.MM–HH.MM WIB:</strong> Deskripsi kegiatan...</p>
   atau
   <p><strong>Pukul HH.MM WIB:</strong> Deskripsi kegiatan...</p>
5. RANGKAI ESTIMASI JAM SECARA REALISTIS DAN LOGIS DARI PAGI SAMPAI PULANG:
   - Jam keberangkatan dari Kantor BPS Kabupaten Demak (pukul 08.00 WIB) dan jam tiba di lokasi/desa sasaran.
   - Jam koordinasi di kantor desa dengan aparat desa setempat, jam briefing lapangan dengan tim/petugas.
   - Jam pelaksanaan kunjungan RT/responden dan wawancara/pemeriksaan pagi hari.
   - WAJIB sertakan waktu istirahat, salat, dan makan siang (ISHOMA) pada pukul 12.00–13.00 WIB.
   - Jam kelanjutan kegiatan lapangan siang hari (pukul 13.00–15.00/15.30 WIB).
   - WAJIB sebutkan jam saat seluruh rangkaian kegiatan lapangan selesai dan petugas memulai perjalanan pulang menuju Kantor BPS Kabupaten Demak.
   - WAJIB sebutkan jam saat tiba kembali di Kantor BPS Kabupaten Demak (sekitar pukul 16.00/16.30 WIB) dan menyelesaikan laporan tugas dinas.
6. Bahasa: Bahasa Indonesia resmi kedinasan BPS yang formal, tertib, dan akuntabel.
7. Istilah BPS: Pertahankan dan gunakan secara tepat istilah teknis survei statistik bila relevan (misal: responden, sampel, komoditas, PPL, PML, Koseka, Task Force, INNAS, Fasih, CAPI, PAPI, blok sensus, dll.).
8. Deteksi Pejabat & Desa:
   - Jika pada catatan pegawai terdeteksi nama pejabat/tokoh (misal kades, sekdes, ketua RT/RW, responden utama), ekstrak ke field "nama_pejabat" beserta jabatannya (misal: "Bapak Mufna'am (Sekretaris Desa Morodemak)").
   - Jika terdeteksi nama desa/kecamatan/lokasi, ekstrak ke field "desa_pejabat" (misal: "Desa Morodemak").

HASIL HARUS DALAM FORMAT JSON BERSIH BERIKUT (tanpa markdown backticks):
{
  "narasi": "<p><strong>Pukul 08.00–09.00 WIB:</strong> ...</p><p><strong>Pukul 09.00–09.45 WIB:</strong> ...</p>",
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

    /**
     * Generate narasi kronologis LPD terpadu dari kumpulan bukti dukung harian (DailyActivity).
     * Menyintesis multi-kegiatan satu hari menjadi 4 segmen waktu resmi BPS:
     * Keberangkatan, Pelaksanaan Substansi/Koordinasi Teknis, ISHOMA, dan Kepulangan.
     *
     * @param iterable<\App\Models\DailyActivity> $activities
     * @param \App\Models\SuratTugas $suratTugas
     * @param \App\Models\User $user
     * @param \Carbon\Carbon $activityDate
     * @return array{narasi: string, nama_pejabat: ?string, desa_pejabat: ?string, summary_items: array}
     */
    public function generateChronologicalFromActivities($activities, SuratTugas $suratTugas, User $user, \Carbon\Carbon $activityDate): array
    {
        $activitiesCol = collect($activities)->sortBy(function ($act) {
            $firstAttTime = $act->attachments->first()?->captured_at;
            if ($firstAttTime) return \Carbon\Carbon::parse($firstAttTime)->timestamp;
            if ($act->started_at) return \Carbon\Carbon::parse($act->activity_date->format('Y-m-d') . ' ' . $act->started_at)->timestamp;
            return $act->created_at?->timestamp ?? 0;
        });

        // 1. Kumpulkan poin kegiatan dan estimasi jam
        $poinList = [];
        $totalPhotos = 0;
        foreach ($activitiesCol as $idx => $act) {
            $timeStr = "";
            $firstAtt = $act->attachments->first();
            if ($firstAtt && $firstAtt->captured_at) {
                $timeStr = " (Foto jam " . \Carbon\Carbon::parse($firstAtt->captured_at)->format('H:i') . " WIB)";
            } elseif ($act->started_at) {
                $timeStr = " (Pukul " . substr($act->started_at, 0, 5) . " WIB)";
            }

            $raw = !empty($act->raw_content) && $act->raw_content !== 'Dokumentasi kegiatan'
                ? $act->raw_content
                : ($act->final_content ?: 'Dokumentasi kegiatan lapangan');

            $totalPhotos += $act->attachments->count();
            $poinList[] = "- [Bukti Dukung " . ($idx + 1) . "{$timeStr}]: " . $raw;
        }

        $gabunganCatatan = implode("\n", $poinList);
        if (empty(trim($gabunganCatatan))) {
            $gabunganCatatan = "- Pelaksanaan tugas kedinasan sesuai Surat Tugas No. " . ($suratTugas->nomor_surat_tugas ?: 'DRAFT');
        }

        $surveyName = $suratTugas->survey?->name ?? 'Kegiatan Dinas BPS';
        $tujuan = $suratTugas->keperluan ?: $suratTugas->tempat_tugas ?: 'Pelaksanaan Penugasan Lapangan';
        $tanggalFormatted = $activityDate->translatedFormat('l, d F Y');
        $desaTujuan = $suratTugas->tempat_tugas ?: 'Kabupaten Demak';

        $apiKey = $this->getApiKey();

        // 2. Jika ada API Key Gemini, susun prompt khusus kronologis BPS
        if (!empty($apiKey)) {
            $prompt = <<<EOT
Anda adalah asisten AI resmi Badan Pusat Statistik (BPS) Kabupaten Demak.
Tugas Anda adalah menyusun narasi 'Uraian Kegiatan' untuk Laporan Perjalanan Dinas (LPD) yang formal, terstruktur kronologis per jam, berwibawa, dan akuntabel berdasarkan kumpulan catatan bukti dukung harian pegawai pada hari tugas tersebut.

DATA PENUGASAN:
- Nama Pegawai: {$user->name}
- Kegiatan/Survei: {$surveyName}
- Keperluan Surat Tugas: {$tujuan}
- Lokasi Tujuan: {$desaTujuan}
- Tanggal Pelaksanaan: {$tanggalFormatted}
- Total Berkas Foto Tersedia: {$totalPhotos} foto

CATATAN BUKTI DUKUNG DARI LAPANGAN PADA HARI INI:
{$gabunganCatatan}

ATURAN STRUKTUR & GAYA BAHASA (MUTLAK):
1. DILARANG KERAS menggunakan tag <ul>, <ol>, <li>, simbol bullet (•, -, *), maupun nomor urut (1., 2., dst.).
2. Setiap sesi jam HARUS menjadi paragraf <p> tersendiri.
3. Awali setiap paragraf dengan jam tebal, contoh: <p><strong>Pukul HH.MM–HH.MM WIB:</strong> ...</p>
4. STRUKTUR HARUS RUNTUT MENJADI 4 SEGMEN WAKTU:
   - SEGMEN 1 (Pagi): Perjalanan keberangkatan petugas dari Kabupaten Demak menuju tempat penugasan.
   - SEGMEN 2 (Pagi-Siang): Pelaksanaan substansi kegiatan utama, koordinasi teknis dengan pejabat/narasumber yang ditemui, poin-poin hasil pembahasan/kesepakatan, dan tindak lanjut lapangan. Uraikan secara spesifik dan teknis sesuai catatan.
   - SEGMEN 3 (Siang): Waktu istirahat, salat, dan makan siang (ISHOMA) sekitar pukul 12.00–13.00 WIB, serta kelanjutan kegiatan setelahnya.
   - SEGMEN 4 (Sore): Seluruh rangkaian kegiatan selesai, petugas bertolak kembali dalam perjalanan menuju Kabupaten Demak dan menyelesaikan administrasi pelaporan perjalanan dinas.
5. Bahasa: Bahasa Indonesia resmi kedinasan BPS yang mengalir, lugas, dan akuntabel.
6. Deteksi Nama Pejabat & Lokasi Pejabat dari catatan di atas jika ada yang disebutkan.

HASIL HARUS DALAM FORMAT JSON BERSIH BERIKUT (tanpa markdown backtick):
{
  "narasi": "<p><strong>Pukul 06.00–08.00 WIB:</strong> ...</p><p><strong>Pukul 08.00–11.30 WIB:</strong> ...</p><p><strong>Pukul 11.30–13.30 WIB:</strong> ...</p><p><strong>Pukul 13.30–15.30 WIB:</strong> ...</p>",
  "nama_pejabat": "Nama pejabat yang ditemui beserta jabatan atau '-' jika tidak spesifik",
  "desa_pejabat": "Nama instansi/lokasi/desa pejabat atau '{$desaTujuan}'"
}
EOT;

            try {
                $model = $this->getModel();
                $modelsToTry = array_unique([$model, 'gemini-3.1-flash-lite', 'gemini-3.5-flash', 'gemini-3.8-flash']);
                $response = null;

                foreach ($modelsToTry as $currentModel) {
                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$currentModel}:generateContent?key={$apiKey}";
                    try {
                        $res = Http::withHeaders(['Content-Type' => 'application/json'])
                            ->timeout(25)
                            ->post($endpoint, [
                                'contents' => [
                                    ['role' => 'user', 'parts' => [['text' => $prompt]]]
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
                    } catch (\Throwable $e) {
                        Log::warning("Gemini chronological LPD model {$currentModel} failed: " . $e->getMessage());
                    }
                }

                if ($response && $response->successful()) {
                    $rawText = $response->json('candidates.0.content.parts.0.text');
                    $decoded = json_decode($rawText, true);
                    if (!is_array($decoded) || !isset($decoded['narasi'])) {
                        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
                        $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
                        $decoded = json_decode($cleaned, true);
                    }

                    if (is_array($decoded) && !empty($decoded['narasi'])) {
                        return [
                            'narasi' => $decoded['narasi'],
                            'nama_pejabat' => !empty($decoded['nama_pejabat']) && $decoded['nama_pejabat'] !== '-' ? $decoded['nama_pejabat'] : null,
                            'desa_pejabat' => !empty($decoded['desa_pejabat']) ? $decoded['desa_pejabat'] : $desaTujuan,
                            'summary_items' => $poinList,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('LpdAiService chronological generation error: ' . $e->getMessage());
            }
        }

        // 3. Fallback Kronologis Lokal Terstruktur (jika tanpa AI atau API error)
        $narasiFallback = "<p><strong>Pukul 07.30–08.30 WIB:</strong> Petugas melakukan perjalanan dari Kantor BPS Kabupaten Demak menuju lokasi tujuan di {$desaTujuan} dalam rangka melaksanakan agenda {$tujuan}.</p>";
        $narasiFallback .= "<p><strong>Pukul 08.30–12.00 WIB:</strong> Pelaksanaan koordinasi teknis dan kegiatan penugasan lapangan: " . htmlspecialchars(implode('. ', array_map(fn($p) => preg_replace('/^- \[.*?\]:\s*/', '', $p), $poinList))) . ".</p>";
        $narasiFallback .= "<p><strong>Pukul 12.00–13.00 WIB:</strong> Petugas melaksanakan istirahat, salat, dan makan siang (ISHOMA) di sekitar lokasi kegiatan penugasan.</p>";
        $narasiFallback .= "<p><strong>Pukul 13.00–15.00 WIB:</strong> Melanjutkan evaluasi penyelesaian pekerjaan serta perapian berkas dokumentasi dan bukti dukung lapangan.</p>";
        $narasiFallback .= "<p><strong>Pukul 15.00–16.30 WIB:</strong> Seluruh rangkaian kegiatan telah selesai, petugas bertolak kembali menuju Kantor BPS Kabupaten Demak dan segera menyelesaikan administrasi laporan perjalanan dinas.</p>";

        return [
            'narasi' => $narasiFallback,
            'nama_pejabat' => null,
            'desa_pejabat' => $desaTujuan,
            'summary_items' => $poinList,
        ];
    }
}
