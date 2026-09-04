<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateImageService
{
    /**
     * Get Base64 data URI for an image path in the public disk,
     * converting WebP to JPEG/PNG if necessary for DomPDF compatibility.
     *
     * @param string|null $path
     * @param string $defaultMime
     * @return string|null
     */
    public static function getBase64Image(?string $path, string $defaultMime = 'image/jpeg'): ?string
    {
        if (!$path) {
            return null;
        }

        $disk = Storage::disk('public');

        // Check if an equivalent JPG or PNG already exists on disk
        if (str_ends_with(strtolower($path), '.webp')) {
            $jpgPath = preg_replace('/\.webp$/i', '.jpg', $path);
            if ($disk->exists($jpgPath)) {
                return self::encodeDataUri($disk->get($jpgPath), 'image/jpeg');
            }

            $jpegPath = preg_replace('/\.webp$/i', '.jpeg', $path);
            if ($disk->exists($jpegPath)) {
                return self::encodeDataUri($disk->get($jpegPath), 'image/jpeg');
            }

            $pngPath = preg_replace('/\.webp$/i', '.png', $path);
            if ($disk->exists($pngPath)) {
                return self::encodeDataUri($disk->get($pngPath), 'image/png');
            }
        }

        if (!$disk->exists($path)) {
            return null;
        }

        try {
            $data = $disk->get($path);
            $mime = $disk->mimeType($path) ?? $defaultMime;

            // If image is WebP, convert to standard image format (JPEG/PNG)
            if (str_ends_with(strtolower($path), '.webp') || $mime === 'image/webp') {
                $converted = self::convertWebpToStandardImage($data, $path);
                if ($converted) {
                    $data = $converted['data'];
                    $mime = $converted['mime'];
                } else {
                    Log::warning("Certificate image [{$path}] is in WebP format, but the current PHP environment lacks WebP support (neither GD with libwebp nor Imagick is available). DomPDF cannot render WebP.");
                    return null;
                }
            }

            // Ensure mime is strictly DomPDF compatible
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
                $mime = 'image/jpeg';
            }

            return self::encodeDataUri($data, $mime);
        } catch (\Throwable $e) {
            Log::error("Failed to process certificate image [{$path}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Encode binary string to data URI base64.
     */
    public static function encodeDataUri(string $data, string $mime): string
    {
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
     * Attempt to convert WebP binary to JPEG or PNG using available extensions/tools.
     */
    public static function convertWebpToStandardImage(string $rawData, ?string $originalPath = null): ?array
    {
        // 1. Try Imagick
        if (class_exists(\Imagick::class)) {
            try {
                $imagick = new \Imagick();
                $imagick->readImageBlob($rawData);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                $jpegData = $imagick->getImageBlob();
                $imagick->clear();
                $imagick->destroy();

                if (!empty($jpegData)) {
                    self::maybeCacheConverted($originalPath, $jpegData, 'jpg');
                    return ['data' => $jpegData, 'mime' => 'image/jpeg'];
                }
            } catch (\Throwable $e) {
                Log::debug('Imagick WebP conversion failed: ' . $e->getMessage());
            }
        }

        // 2. Try GD (ONLY if GD supports WebP to avoid "No WEBP support in this PHP build")
        if (self::gdSupportsWebp()) {
            try {
                $im = @imagecreatefromstring($rawData);
                if ($im !== false) {
                    ob_start();
                    imagejpeg($im, null, 90);
                    $jpegData = ob_get_clean();
                    imagedestroy($im);

                    if (!empty($jpegData)) {
                        self::maybeCacheConverted($originalPath, $jpegData, 'jpg');
                        return ['data' => $jpegData, 'mime' => 'image/jpeg'];
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('GD WebP conversion failed: ' . $e->getMessage());
            }
        }

        // 3. Try CLI tools (dwebp or ImageMagick convert) if exec is available
        if (function_exists('exec') && !self::isFunctionDisabled('exec')) {
            try {
                $tempWebp = tempnam(sys_get_temp_dir(), 'webp_');
                $tempJpg = tempnam(sys_get_temp_dir(), 'jpg_') . '.jpg';
                file_put_contents($tempWebp, $rawData);

                // Try dwebp
                $output = [];
                $ret = -1;
                @exec("dwebp " . escapeshellarg($tempWebp) . " -o " . escapeshellarg($tempJpg) . " 2>&1", $output, $ret);
                if ($ret === 0 && file_exists($tempJpg) && filesize($tempJpg) > 0) {
                    $jpegData = file_get_contents($tempJpg);
                    @unlink($tempWebp);
                    @unlink($tempJpg);
                    self::maybeCacheConverted($originalPath, $jpegData, 'jpg');
                    return ['data' => $jpegData, 'mime' => 'image/jpeg'];
                }

                // Try ImageMagick convert CLI
                $output = [];
                $ret = -1;
                @exec("convert " . escapeshellarg($tempWebp) . " " . escapeshellarg($tempJpg) . " 2>&1", $output, $ret);
                if ($ret === 0 && file_exists($tempJpg) && filesize($tempJpg) > 0) {
                    $jpegData = file_get_contents($tempJpg);
                    @unlink($tempWebp);
                    @unlink($tempJpg);
                    self::maybeCacheConverted($originalPath, $jpegData, 'jpg');
                    return ['data' => $jpegData, 'mime' => 'image/jpeg'];
                }

                @unlink($tempWebp);
                if (file_exists($tempJpg)) {
                    @unlink($tempJpg);
                }
            } catch (\Throwable $e) {
                Log::debug('CLI WebP conversion failed: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Check if GD extension actually has WebP support compiled in.
     */
    public static function gdSupportsWebp(): bool
    {
        return function_exists('imagetypes') &&
               defined('IMG_WEBP') &&
               ((imagetypes() & IMG_WEBP) !== 0) &&
               function_exists('imagecreatefromwebp');
    }

    /**
     * Check if a PHP function is disabled in php.ini.
     */
    private static function isFunctionDisabled(string $function): bool
    {
        $disabled = explode(',', (string) ini_get('disable_functions'));
        return in_array($function, array_map('trim', $disabled), true);
    }

    /**
     * Cache converted file so next time it loads directly.
     */
    private static function maybeCacheConverted(?string $originalPath, string $convertedData, string $ext): void
    {
        if (!$originalPath) {
            return;
        }

        try {
            $newPath = preg_replace('/\.[^.]+$/', '.' . $ext, $originalPath);
            if ($newPath !== $originalPath) {
                Storage::disk('public')->put($newPath, $convertedData);

                // If this is a template background or signer, update template path to avoid future conversions
                CertificateTemplate::where('background_path', $originalPath)
                    ->update(['background_path' => $newPath]);

                CertificateTemplate::where('signer_image_path', $originalPath)
                    ->update(['signer_image_path' => $newPath]);
            }
        } catch (\Throwable $e) {
            // ignore cache write error
        }
    }
}
