<?php

namespace App\Console\Commands;

use App\Models\CertificateTemplate;
use App\Services\CertificateImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FixCertificateWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:fix-webp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa dan konversi template sertifikat WebP ke JPG/PNG agar kompatibel dengan DomPDF di semua server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memeriksa template sertifikat...');

        $templates = CertificateTemplate::all();
        $disk = Storage::disk('public');
        $updatedCount = 0;

        foreach ($templates as $template) {
            $this->line("Template #{$template->id}: {$template->name}");

            // 1. Check Background
            if ($template->background_path && str_ends_with(strtolower($template->background_path), '.webp')) {
                $oldPath = $template->background_path;
                $jpgPath = preg_replace('/\.webp$/i', '.jpg', $oldPath);

                if ($disk->exists($jpgPath)) {
                    $template->background_path = $jpgPath;
                    $template->save();
                    $this->info("  - Background diperbarui ke file JPG yang sudah ada: {$jpgPath}");
                    $updatedCount++;
                } elseif ($disk->exists($oldPath)) {
                    $converted = CertificateImageService::convertWebpToStandardImage($disk->get($oldPath), $oldPath);
                    if ($converted) {
                        $template->refresh();
                        $this->info("  - Background WebP berhasil dikonversi ke JPG: {$template->background_path}");
                        $updatedCount++;
                    } else {
                        $this->warn("  - Background [{$oldPath}] berformat WebP, tetapi server tidak memiliki modul WebP (GD tanpa libwebp & tidak ada Imagick).");
                        $this->warn("    Solusi: Silakan re-upload file background berformat JPG atau PNG via menu Certificate Template di Filament Admin.");
                    }
                } else {
                    $this->warn("  - File background tidak ditemukan di storage: {$oldPath}");
                }
            }

            // 2. Check Signer Image
            if ($template->signer_image_path && str_ends_with(strtolower($template->signer_image_path), '.webp')) {
                $oldSign = $template->signer_image_path;
                $pngPath = preg_replace('/\.webp$/i', '.png', $oldSign);

                if ($disk->exists($pngPath)) {
                    $template->signer_image_path = $pngPath;
                    $template->save();
                    $this->info("  - Signer diperbarui ke file PNG yang sudah ada: {$pngPath}");
                    $updatedCount++;
                } elseif ($disk->exists($oldSign)) {
                    $converted = CertificateImageService::convertWebpToStandardImage($disk->get($oldSign), $oldSign);
                    if ($converted) {
                        $template->refresh();
                        $this->info("  - Signer WebP berhasil dikonversi: {$template->signer_image_path}");
                        $updatedCount++;
                    } else {
                        $this->warn("  - Signer [{$oldSign}] berformat WebP dan tidak dapat dikonversi otomatis.");
                    }
                }
            }
        }

        $this->info("Selesai! {$updatedCount} template diperbarui.");
        return 0;
    }
}
