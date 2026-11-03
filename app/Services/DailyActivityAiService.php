<?php

namespace App\Services;

use App\Settings\SystemSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DailyActivityAiService
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

        return config('services.gemini.model', 'gemini-2.5-flash');
    }

    /**
     * Memoles catatan harian santai menjadi uraian kegiatan formal untuk bukti dukung kerja.
     *
     * @param string $rawText
     * @param array<string, mixed> $context
     * @return string
     * @throws Exception
     */
    public function formalize(string $rawText, array $context = []): string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception("API Key Gemini belum dikonfigurasi di Pengaturan Sistem.");
        }

        $petugasName = $context['user_name'] ?? 'Pegawai';
        $tanggal = $context['tanggal'] ?? date('d-m-Y');
        $suratTugas = $context['surat_tugas'] ?? null;
        $stInfo = $suratTugas ? "Terkait Penugasan: {$suratTugas}" : "Kegiatan Rutin / Non-Surat Tugas";

        $prompt = <<<EOT
Anda adalah asisten AI resmi Badan Pusat Statistik (BPS) Kabupaten Demak.
Tugas Anda adalah merapikan dan memoles catatan kegiatan kerja harian santai dari pegawai menjadi uraian kegiatan formal yang baku, akuntabel, dan profesional untuk format bukti dukung laporan kinerja harian / SKP.

INFORMASI KONTEKS:
- Nama Pegawai: {$petugasName}
- Tanggal Kegiatan: {$tanggal}
- Keterangan Penugasan: {$stInfo}

CATATAN ASLI PEGAWAI:
"{$rawText}"

PETUNJUK FORMALISASI (MUTLAK):
1. Ubah bahasa percakapan santai, singkatan, atau draft kasar menjadi kalimat laporan kedinasan yang aktif, formal, dan santun (Bahasa Indonesia baku).
2. DILARANG KERAS mengarang/menambahkan fakta fiktif (nama orang, lokasi, dokumen, angka) yang tidak disebutkan atau tidak secara logis tersirat dari catatan asli.
3. Struktur kalimat diawali dengan kata kerja operasional yang tepat (misal: "Melaksanakan...", "Menyusun...", "Melakukan koordinasi...", "Memeriksa kelengkapan...", "Melakukan pemeliharaan...", "Menginput data...").
4. Pertahankan istilah statistik/teknis bila ada (misal: CAPI, Fasih, PPL, PML, Regsosek, Sakernas, Susenas, LAN, printer, rekonsiliasi, dll.).
5. Hasil akhir adalah 1-2 paragraf padat dan siap dicantumkan langsung ke dokumen laporan. Jangan sertakan sapaan, pembuka, penutup, atau tanda kutip.

Keluarkan HANYA teks formal hasil polesan tanpa komentar tambahan.
EOT;

        $model = $this->getModel();
        $modelsToTry = array_unique([$model, 'gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-2.0-flash']);

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
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1024,
                    ]
                ]);

                if ($res->successful()) {
                    $json = $res->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($text)) {
                        return trim($text);
                    }
                }

                $lastError = "Model {$currentModel} HTTP " . $res->status() . ": " . $res->body();
            } catch (Exception $e) {
                $lastError = "Model {$currentModel} exception: " . $e->getMessage();
            }
        }

        Log::error("DailyActivityAiService failed: " . $lastError);
        throw new Exception("Gagal memoles narasi dengan AI: " . ($lastError ?: 'Respon kosong dari API'));
    }

    /**
     * Mentranskripsikan dan memoles rekaman suara (Voice Note) menjadi catatan dan narasi formal.
     *
     * @param string $audioPath
     * @param string $mimeType
     * @param array<string, mixed> $context
     * @return array{raw_transcript: string, formal_content: string}
     */
    public function formalizeAudio(string $audioPath, string $mimeType = 'audio/ogg', array $context = []): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception("API Key Gemini belum dikonfigurasi di Pengaturan Sistem.");
        }

        if (!file_exists($audioPath) || !is_readable($audioPath)) {
            throw new Exception("File audio tidak ditemukan di server.");
        }

        $base64Data = base64_encode(file_get_contents($audioPath));
        $petugasName = $context['user_name'] ?? 'Pegawai';
        $tanggal = $context['tanggal'] ?? date('d-m-Y');

        $prompt = <<<EOT
Anda adalah asisten AI resmi Badan Pusat Statistik (BPS) Kabupaten Demak.
Dengarkan rekaman suara (voice note) berikut yang dikirim oleh pegawai BPS: {$petugasName} pada {$tanggal}.

TUGAS ANDA:
1. Dengarkan dan transkripsikan rekaman audio dengan tepat apa adanya ke Bahasa Indonesia.
2. Formulasikan hasil transkripsi tersebut menjadi 1-2 kalimat narasi laporan kinerja kedinasan formal (Bahasa Indonesia baku, diawali kata kerja aktif operasional seperti "Melaksanakan...", "Melakukan koordinasi...", "Menyusun...", dsb).

Format output WAJIB JSON:
{"transcript": "transkrip rekaman asli", "formal": "narasi laporan formal"}
Keluarkan HANYA JSON tanpa markdown block ```json atau komentar apapun.
EOT;

        $model = $this->getModel();
        $modelsToTry = array_unique([$model, 'gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-2.0-flash']);
        $lastError = null;

        foreach ($modelsToTry as $currentModel) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$currentModel}:generateContent?key={$apiKey}";

            try {
                $res = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(35)->post($endpoint, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Data,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 1024,
                    ]
                ]);

                if ($res->successful()) {
                    $json = $res->json();
                    $rawResponse = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($rawResponse)) {
                        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($rawResponse)));
                        $decoded = json_decode($clean, true);
                        if (json_last_error() === JSON_ERROR_NONE && !empty($decoded['formal'])) {
                            return [
                                'raw_transcript' => trim($decoded['transcript'] ?? $clean),
                                'formal_content' => trim($decoded['formal']),
                            ];
                        }

                        return [
                            'raw_transcript' => $clean,
                            'formal_content' => $clean,
                        ];
                    }
                }

                $lastError = "Model {$currentModel} HTTP " . $res->status() . ": " . $res->body();
            } catch (Exception $e) {
                $lastError = "Model {$currentModel} exception: " . $e->getMessage();
            }
        }

        Log::error("DailyActivityAiService Audio failed: " . $lastError);
        throw new Exception("Gagal memproses rekaman suara dengan AI: " . ($lastError ?: 'Respon kosong'));
    }

    /**
     * Merangkum seluruh kegiatan harian dalam satu wadah KIPAPP menjadi 1-2 paragraf ringkasan capaian SKP / E-Kinerja.
     *
     * @param \Illuminate\Support\Collection|array $activities
     * @param string $containerTitle
     * @return string
     */
    public function generateMonthlySummary($activities, string $containerTitle): string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new Exception("API Key Gemini belum dikonfigurasi di Pengaturan Sistem.");
        }

        $items = collect($activities)->map(function ($act, $i) {
            $tgl = $act->activity_date ? \Carbon\Carbon::parse($act->activity_date)->format('d/m/Y') : '-';
            $konten = $act->final_content ?: $act->raw_content;
            return ($i + 1) . ". [{$tgl}] {$konten}";
        })->implode("\n");

        if (empty(trim($items))) {
            return "Belum ada catatan kegiatan harian yang cukup untuk dirangkum.";
        }

        $prompt = <<<EOT
Anda adalah asisten AI profesional untuk evaluasi kinerja pegawai Badan Pusat Statistik (BPS).
Berikut adalah log kegiatan harian yang telah dilaksanakan oleh pegawai untuk butir kegiatan SKP: "{$containerTitle}".

LOG KEGIATAN:
{$items}

TUGAS:
Sintesis dan rangkum seluruh kegiatan di atas menjadi 1 hingga maksimal 2 paragraf padat, komprehensif, dan elegan untuk diisikan pada kotak "Realisasi / Uraian Capaian Kinerja" pada aplikasi E-Kinerja (KIPAPP) BPS.
Gunakan Bahasa Indonesia baku kedinasan aktif (contoh: "Telah diselesaikan serangkaian kegiatan pengolahan dan validasi peta wilayah kerja statistik..."). Jangan gunakan bullet point, pembuka, atau tanda kutip.
EOT;

        $model = $this->getModel();
        $modelsToTry = array_unique([$model, 'gemini-2.5-flash', 'gemini-1.5-flash', 'gemini-2.0-flash']);
        $lastError = null;

        foreach ($modelsToTry as $currentModel) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$currentModel}:generateContent?key={$apiKey}";

            try {
                $res = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($endpoint, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 1024,
                    ]
                ]);

                if ($res->successful()) {
                    $json = $res->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($text)) {
                        return trim($text);
                    }
                }

                $lastError = "Model {$currentModel} HTTP " . $res->status();
            } catch (Exception $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new Exception("Gagal membuat ringkasan AI: " . $lastError);
    }
}
