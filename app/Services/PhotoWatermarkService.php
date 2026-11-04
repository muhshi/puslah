<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PhotoWatermarkService
{
    /**
     * Terapkan watermark stempel koordinat dan waktu permanen ke file gambar menggunakan PHP GD.
     * Menggunakan TrueType font (Montserrat/DejaVu) jika tersedia untuk hasil super tajam mirip Open Camera.
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
            
            $capturedAt = $params['captured_at'] ?? now('Asia/Jakarta');
            if (is_string($capturedAt)) {
                try {
                    $capturedAt = Carbon::parse($capturedAt, 'Asia/Jakarta');
                } catch (\Throwable $e) {
                    $capturedAt = now('Asia/Jakarta');
                }
            }
            $timeStr = ($capturedAt instanceof Carbon)
                ? $capturedAt->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i:s') . ' WIB'
                : (string) $capturedAt;

            $caption = trim($params['caption'] ?? 'Dokumentasi Kegiatan');
            if (empty($caption) || strtolower($caption) === 'dokumentasi kegiatan') {
                $caption = 'Dokumentasi Pelaksanaan Tugas Lapangan';
            }
            // Batasi panjang caption agar tidak overflow
            $caption = mb_strimwidth($caption, 0, 55, '...');

            $office = trim($params['office_name'] ?? 'BPS KABUPATEN DEMAK');

            // 2. Baris teks stempel
            $lines = [
                ['text' => 'STEMPEL RESMI BUKTI DUKUNG', 'type' => 'header'],
                ['text' => "Lat: {$lat}  Long: {$lng}", 'type' => 'coord'],
                ['text' => "Waktu: {$timeStr}", 'type' => 'time'],
                ['text' => "Kegiatan: {$caption}", 'type' => 'caption'],
                ['text' => $office, 'type' => 'office'],
            ];

            imagealphablending($image, true);
            imagesavealpha($image, true);

            // 3. Tentukan font: prioritaskan TrueType font (.ttf) untuk hasil anti-aliasing tajam
            $fontPath = null;
            $possibleFonts = [
                storage_path('fonts/Montserrat/static/Montserrat-Bold.ttf'),
                base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
                storage_path('fonts/Montserrat-Bold.ttf'),
            ];
            foreach ($possibleFonts as $f) {
                if (file_exists($f)) {
                    $fontPath = $f;
                    break;
                }
            }

            // Alokasikan warna
            $bgColor = imagecolorallocatealpha($image, 15, 20, 30, 28); // Hitam pekat ~80% opacity
            $yellow = imagecolorallocate($image, 253, 224, 71); // Kuning terang
            $white = imagecolorallocate($image, 255, 255, 255);
            $cyan = imagecolorallocate($image, 103, 232, 249);  // Cyan
            $green = imagecolorallocate($image, 74, 222, 128);  // Mint green

            if ($fontPath && function_exists('imagettftext') && function_exists('imagettfbbox')) {
                // Skala font dinamis proporsional terhadap resolusi foto
                $fontSize = max(10, min(24, (int) round($width / 80)));
                $lineSpacing = (int) round($fontSize * 1.55);
                $padX = (int) round($fontSize * 1.2);
                $padY = (int) round($fontSize * 1.0);

                // Hitung lebar kotak berdasarkan teks terpanjang
                $maxTextWidth = 0;
                foreach ($lines as $item) {
                    $box = imagettfbbox($fontSize, 0, $fontPath, $item['text']);
                    $itemW = abs($box[4] - $box[0]);
                    if ($itemW > $maxTextWidth) {
                        $maxTextWidth = $itemW;
                    }
                }

                $boxWidth = $maxTextWidth + ($padX * 2);
                $boxHeight = (count($lines) * $lineSpacing) + ($padY * 2);

                // Hitung posisi stempel sesuai opsi (Smart Position: default bottom-right agar tidak menimpa watermark ponsel)
                $position = $params['position'] ?? 'bottom-right';
                if ($position === 'bottom-right') {
                    $boxX = $width - $boxWidth - 16;
                    $boxY = $height - $boxHeight - 16;
                } elseif ($position === 'top-left') {
                    $boxX = 16;
                    $boxY = 16;
                } elseif ($position === 'top-right') {
                    $boxX = $width - $boxWidth - 16;
                    $boxY = 16;
                } else {
                    $boxX = 16;
                    $boxY = $height - $boxHeight - 16;
                }
                if ($boxX < 10) $boxX = 10;
                if ($boxY < 10) $boxY = 10;

                // Gambar background box
                imagefilledrectangle($image, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $bgColor);

                // Tulis teks dengan imagettftext
                $textX = $boxX + $padX;
                $textY = $boxY + $padY + $fontSize;

                foreach ($lines as $item) {
                    $color = match ($item['type']) {
                        'header' => $green,
                        'coord', 'time' => $yellow,
                        'caption' => $white,
                        'office' => $cyan,
                        default => $white,
                    };

                    imagettftext($image, $fontSize, 0, $textX, $textY, $color, $fontPath, $item['text']);
                    $textY += $lineSpacing;
                }
            } else {
                // Fallback GD built-in font
                $font = ($width >= 1200) ? 5 : 4;
                $charWidth = imagefontwidth($font);
                $charHeight = imagefontheight($font);
                $lineHeight = $charHeight + 5;

                $maxChars = 0;
                foreach ($lines as $l) {
                    $maxChars = max($maxChars, strlen($l['text']));
                }

                $boxWidth = ($maxChars * $charWidth) + 24;
                $boxHeight = (count($lines) * $lineHeight) + 16;

                // Hitung posisi stempel sesuai opsi (Smart Position fallback: default bottom-right)
                $position = $params['position'] ?? 'bottom-right';
                if ($position === 'bottom-right') {
                    $boxX = $width - $boxWidth - 14;
                    $boxY = $height - $boxHeight - 14;
                } elseif ($position === 'top-left') {
                    $boxX = 14;
                    $boxY = 14;
                } elseif ($position === 'top-right') {
                    $boxX = $width - $boxWidth - 14;
                    $boxY = 14;
                } else {
                    $boxX = 14;
                    $boxY = $height - $boxHeight - 14;
                }
                if ($boxX < 10) $boxX = 10;
                if ($boxY < 10) $boxY = 10;

                imagefilledrectangle($image, $boxX, $boxY, $boxX + $boxWidth, $boxY + $boxHeight, $bgColor);

                $currentY = $boxY + 8;
                $textX = $boxX + 12;

                foreach ($lines as $item) {
                    $color = match ($item['type']) {
                        'header' => $green,
                        'coord', 'time' => $yellow,
                        'caption' => $white,
                        'office' => $cyan,
                        default => $white,
                    };
                    imagestring($image, $font, $textX, $currentY, $item['text'], $color);
                    $currentY += $lineHeight;
                }
            }

            // 4. Simpan kembali file gambar fisik (timpa file asli)
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if ($ext === 'png') {
                imagepng($image, $fullPath, 8);
            } elseif ($ext === 'webp') {
                imagewebp($image, $fullPath, 92);
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
