<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

class ImageExifExtractorService
{
    /**
     * Ekstrak metadata GPS dan Waktu Pengambilan dari file foto.
     *
     * @param string $filePath Path absolut atau relatif di storage
     * @return array{latitude: ?float, longitude: ?float, captured_at: ?Carbon}
     */
    public function extract(string $filePath): array
    {
        $result = [
            'latitude' => null,
            'longitude' => null,
            'captured_at' => null,
        ];

        if (!file_exists($filePath) || !function_exists('exif_read_data')) {
            return $result;
        }

        try {
            $exif = @exif_read_data($filePath, 'IFD0,EXIF,GPS');
            if (!$exif || !is_array($exif)) {
                return $result;
            }

            // 1. Ekstrak Waktu Pengambilan Foto
            $dateString = $exif['DateTimeOriginal'] ?? $exif['DateTimeDigitized'] ?? $exif['DateTime'] ?? null;
            if ($dateString) {
                try {
                    $result['captured_at'] = Carbon::createFromFormat('Y:m:d H:i:s', trim($dateString));
                } catch (Throwable $e) {
                    try {
                        $result['captured_at'] = Carbon::parse($dateString);
                    } catch (Throwable $e2) {
                        // ignore unparseable date
                    }
                }
            }

            // 2. Ekstrak Koordinat GPS (Latitude & Longitude)
            if (isset($exif['GPSLatitude'], $exif['GPSLatitudeRef'], $exif['GPSLongitude'], $exif['GPSLongitudeRef'])) {
                $lat = $this->dmsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
                $lng = $this->dmsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

                if ($lat !== null && $lng !== null) {
                    $result['latitude'] = $lat;
                    $result['longitude'] = $lng;
                }
            }
        } catch (Throwable $e) {
            // EXIF corrupted or unsupported format
        }

        return $result;
    }

    /**
     * Konversi Degrees-Minutes-Seconds (DMS) ke Decimal Degrees (DD).
     */
    protected function dmsToDecimal(mixed $coordinate, string $hemisphere): ?float
    {
        if (!is_array($coordinate) || count($coordinate) < 3) {
            return null;
        }

        $degrees = $this->evalRational($coordinate[0]);
        $minutes = $this->evalRational($coordinate[1]);
        $seconds = $this->evalRational($coordinate[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        $hemisphere = strtoupper(trim($hemisphere));

        if ($hemisphere === 'S' || $hemisphere === 'W') {
            $decimal *= -1;
        }

        return round($decimal, 8);
    }

    /**
     * Hitung nilai rasional fraksi "numerator/denominator".
     */
    protected function evalRational(mixed $fraction): float
    {
        if (is_numeric($fraction)) {
            return (float) $fraction;
        }

        $parts = explode('/', (string) $fraction);
        if (count($parts) === 2 && (float) $parts[1] != 0) {
            return (float) $parts[0] / (float) $parts[1];
        }

        return (float) $fraction;
    }
}
