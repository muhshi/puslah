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
}
