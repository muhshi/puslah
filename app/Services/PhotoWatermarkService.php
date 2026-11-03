<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PhotoWatermarkService
{
    /**
     * Terapkan watermark stempel koordinat dan waktu permanen ke file gambar menggunakan PHP GD.
     *
     * @param string $fullPath Path absolut file gambar
     * @param array{
     *     latitude?: ?float,
     *     longitude?: ?float,
     *     captured_at?: Carbon|string|null,
     *     caption?: ?string,
     *     office_name?: ?string
     * } $params
     * @return bool
     */
    public function applyWatermark(string $fullPath, array $params = []): bool
    {
        if (!file_exists($fullPath) || !function_exists('imagecreatefromstring')) {
            return false;
        }

        try {
            $fileData = @file_get_contents($fullPath);
            if (!$fileData) return false;

            $image = @imagecreatefromstring($fileData);
            if (!$image) return false;

            // 1. Koreksi orientasi EXIF jika ada (mencegah foto HP miring)
            $image = $this->fixOrientation($image, $fullPath);

            $width = imagesx($image);
            $height = imagesy($image);

            // Format parameter
            $lat = !empty($params['latitude']) ? number_format((float) $params['latitude'], 6, '.', '') : '-6.894561';
            $lng = !empty($params['longitude']) ? number_format((float) $params['longitude'], 6, '.', '') : '110.637492';
            
            $capturedAt = $params['captured_at'] ?? now();
            if (is_string($capturedAt)) {
                try {
                    $capturedAt = Carbon::parse($capturedAt);
                } catch (\Throwable $e) {
                    $capturedAt = now();
                }
            }
            $timeStr = ($capturedAt instanceof Carbon)
                ? $capturedAt->translatedFormat('d M Y H:i:s') . ' WIB'
                : (string) $capturedAt;

            $caption = trim($params['caption'] ?? 'Dokumentasi Kegiatan');
            if (empty($caption) || $caption === 'Dokumentasi kegiatan') {
                $caption = 'Dokumentasi Pelaksanaan Tugas Lapangan';
            }
            // Batasi panjang caption agar tidak overflow
            $caption = mb_strimwidth($caption, 0, 60, '...');

            $office = trim($params['office_name'] ?? 'BPS KABUPATEN DEMAK');

            // 2. Siapkan baris teks stempel
            $lines = [
                ['label' => 'STEMPEL RESMI BUKTI DUKUNG', 'type' => 'header'],
                ['text' => "Lat: {$lat}  Long: {$lng}", 'type' => 'coord'],
                ['text' => "Waktu: {$timeStr}", 'type' => 'time'],
                ['text' => "Kegiatan: {$caption}", 'type' => 'caption'],
                ['text' => $office, 'type' => 'office'],
            ];

            // 3. Gambar background overlay semi-transparan di pojok bawah
            imagealphablending($image, true);
            imagesavealpha($image, true);

            // Ukuran font dan kotak stempel (proporsional dengan resolusi foto)
            $font = 4; // Built-in font GD 4 (tajam, terbaca jelas)
            if ($width >= 1600 && $height >= 1200) {
                $font = 5; // Built-in font GD 5 untuk foto resolusi tinggi
            }

            $charWidth = imagefontwidth($font);
            $charHeight = imagefontheight($font);

            $lineHeight = $charHeight + 6;
            $boxHeight = (count($lines) * $lineHeight) + 16;
            $boxWidth = min((int) ($width * 0.85), max(400, (int) ($width * 0.65)));
            if ($boxWidth > $width - 20) {
                $boxWidth = $width - 20;
            }

            $boxX = 12;
            $boxY = $height - $boxHeight - 14;

            // Alokasikan warna
            $bgColor = imagecolorallocatealpha($image, 10, 15, 25, 45); // Gelap 70% opacity
            $borderColor = imagecolorallocatealpha($image, 255, 255, 255, 75);
            $yellow = imagecolorallocate($image, 253, 224, 71); // Kuning terang
            $white = imagecolorallocate($image, 255, 255, 255);
            $cyan = imagecolorallocate($image, 103, 232, 249);  // Cyan
            $green = imagecolorallocate($image, 110, 231, 183); // Mint green

            // Gambar kotak stempel
            imagefilledrectangle($image, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $bgColor);
            imagerectangle($image, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $borderColor);

            // 4. Tulis setiap baris teks
            $currentY = $boxY + 8;
            $textX = $boxX + 12;

            foreach ($lines as $item) {
                if ($item['type'] === 'header') {
                    imagestring($image, $font, $textX, $currentY, "[ " . $item['label'] . " ]", $green);
                } elseif ($item['type'] === 'coord') {
                    imagestring($image, $font, $textX, $currentY, $item['text'], $yellow);
                } elseif ($item['type'] === 'time') {
                    imagestring($image, $font, $textX, $currentY, $item['text'], $yellow);
                } elseif ($item['type'] === 'caption') {
                    imagestring($image, $font, $textX, $currentY, $item['text'], $white);
                } elseif ($item['type'] === 'office') {
                    imagestring($image, $font, $textX, $currentY, $item['text'], $cyan);
                }
                $currentY += $lineHeight;
            }

            // 5. Simpan file gambar hasil stempel (timpa file asli)
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if ($ext === 'png') {
                imagepng($image, $fullPath, 8);
            } elseif ($ext === 'webp') {
                imagewebp($image, $fullPath, 90);
            } else {
                imagejpeg($image, $fullPath, 92);
            }

            imagedestroy($image);
            return true;
        } catch (\Throwable $e) {
            Log::error('PhotoWatermarkService error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Koreksi orientasi gambar berdasarkan EXIF Orientation tag.
     */
    protected function fixOrientation($image, string $fullPath)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($fullPath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        break;
                    case 8:
                        $image = imagerotate($image, 90, 0);
                        break;
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika EXIF tidak terbaca
        }

        return $image;
    }
}
