<?php

namespace App\Jobs;

use App\Models\SuratTugas;
use App\Models\User;
use App\Settings\SystemSettings;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class GenerateBulkSuratTugasZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // Allow up to 10 minutes for huge files

    protected $recordIds;
    protected $type;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $recordIds, string $type, int $userId)
    {
        $this->recordIds = $recordIds;
        $this->type = $type;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Override strict PHP limits (FrankenPHP/Docker usually caps at 30s and 128MB)
        set_time_limit(0);
        ini_set('memory_limit', '1024M'); // Keep safety net

        if (empty($this->recordIds)) {
            return;
        }

        // Ensure exports directory exists
        Storage::disk('public')->makeDirectory('exports');
        
        $isPdf = ($this->type === 'pdf');
        $outputExt = $isPdf ? 'pdf' : 'zip';
        $outputFileName = 'Surat_Tugas_' . strtoupper($this->type) . '_Bulk_' . now()->format('YmdHis') . '.' . $outputExt;
        $outputPath = Storage::disk('public')->path('exports/' . $outputFileName);
        $outputUrl = asset('storage/exports/' . $outputFileName);

        $zip = null;
        if (!$isPdf) {
            $zip = new \ZipArchive();
            if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                $this->notifyUser('Gagal', 'Sistem gagal membuat file ZIP.', 'danger');
                return;
            }
        }

        $tempFiles = [];
        $totalProcessed = 0;

        // Process in chunks of 50 to prevent RAM exhaustion
        $chunks = array_chunk($this->recordIds, 50);
        $totalRecords = count($this->recordIds);

        \Illuminate\Support\Facades\Log::info("Mulai memproses Bulk " . strtoupper($this->type) . " untuk {$totalRecords} data...");

        foreach ($chunks as $chunkIds) {
            $records = SuratTugas::whereIn('id', $chunkIds)->with(['survey', 'user.profile'])->get();
            
            if ($isPdf) {
                $this->processPdf($records, $tempFiles);
            } else {
                $this->processWord($records, $zip, $tempFiles);
            }

            $totalProcessed += $records->count();
            
            \Illuminate\Support\Facades\Log::info("Progres Bulk: {$totalProcessed} / {$totalRecords} selesai.");

            // Clear memory after each chunk
            unset($records);
            gc_collect_cycles();
        }

        if (!$isPdf) {
            $zip->close();
        } else {
            // MERGE PDFs using Ghostscript
            if (count($tempFiles) > 0) {
                // Build gs command with individual file arguments (avoids @file quoting issues)
                $fileArgs = implode(' ', array_map('escapeshellarg', $tempFiles));
                $outputPdfEscaped = escapeshellarg($outputPath);
                
                // Build Ghostscript merge command
                $gsFlags = '-dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dAutoRotatePages=/None';
                
                // Apply PDF encryption on the final merged file if master password is set
                $settings = app(SystemSettings::class);
                if (!empty($settings->pdf_master_password)) {
                    $ownerPass = escapeshellarg($settings->pdf_master_password);
                    $gsFlags .= " -sOwnerPassword={$ownerPass} -dEncryptionR=3 -dPermissions=-3904";
                }
                
                $command = "gs {$gsFlags} -sOutputFile={$outputPdfEscaped} {$fileArgs} 2>&1";
                $output = shell_exec($command);
                
                \Illuminate\Support\Facades\Log::info("Ghostscript merge complete untuk {$outputFileName}. Output: " . ($output ?: 'OK'));
            } else {
                \Illuminate\Support\Facades\Log::warning("Tidak ada file PDF yang dihasilkan untuk dimerge.");
            }
        }

        // Clean up temp files
        foreach ($tempFiles as $tempPath) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        // Send Success Notification
        $user = User::find($this->userId);
        if ($user) {
            try {
                \Illuminate\Support\Facades\Log::info("Mengirim notifikasi ke user #{$this->userId} pada DB: " . \Illuminate\Support\Facades\DB::connection()->getDatabaseName());
                
                \Filament\Notifications\Notification::make()
                    ->title('File Surat Tugas Siap!')
                    ->body('Proses pembuatan ' . $totalProcessed . ' file ' . strtoupper($this->type) . ' telah selesai.')
                    ->success()
                    ->actions([
                        Action::make('download')
                            ->label('Download File')
                            ->button()
                            ->url($outputUrl)
                            ->openUrlInNewTab(),
                    ])
                    ->sendToDatabase($user);
                    
                \Illuminate\Support\Facades\Log::info("Notifikasi Filament berhasil dikirim ke user #{$this->userId}.");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Gagal kirim notifikasi: " . $e->getMessage());
            }
        } else {
            \Illuminate\Support\Facades\Log::warning("User #{$this->userId} tidak ditemukan untuk notifikasi.");
        }
    }

    protected function processWord($records, &$zip, &$tempFiles)
    {
        $settings = app(SystemSettings::class);
        $templatePath = $settings->surat_tugas_template_path;

        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
            $this->notifyUser('Gagal', 'Template Word belum diupload di Pengaturan Sistem.', 'danger');
            return;
        }

        foreach ($records as $record) {
            $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));

            $template->setValue('nomor_surat', $record->nomor_surat);
            $template->setValue('nama_pegawai', $record->user->profile->full_name ?? $record->user->name);
            $template->setValue('nip_pegawai', '-');
            $template->setValue('jabatan_pegawai', $record->user->profile->jabatan ?? '-');
            $template->setValue('jabatan_tugas', $record->jabatan);
            $template->setValue('keperluan', $record->keperluan);
            $template->setValue('dasar_surat', $record->survey?->dasar_surat ?? '-');
            $template->setValue('tempat_tugas', $record->tempat_tugas ?? '-');
            $template->setValue('tanggal_surat', $record->tanggal->translatedFormat('d F Y'));

            $periodeTugas = \App\Filament\Resources\SuratTugasResource::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);
            $template->setValue('periode_tugas', $periodeTugas);

            $template->setValue('nama_kepala', $record->signer_name);
            $template->setValue('nip_kepala', $record->signer_nip);
            $template->setValue('jabatan_kepala', $record->signer_title);
            $template->setValue('kota_penetapan', $record->signer_city);

            $safeFilename = str_replace(['/', '\\'], '_', $record->nomor_surat);
            $fileName = "Surat_Tugas_{$safeFilename}.docx";
            $tempPath = storage_path('app/temp_bulk_word_' . uniqid() . '.docx');
            
            $template->saveAs($tempPath);
            $tempFiles[] = $tempPath;
            $zip->addFile($tempPath, $fileName);
            
            unset($template);
        }
    }

    protected function processPdf($records, &$tempFiles)
    {
        $logoBase64 = \Illuminate\Support\Facades\Cache::remember('logo_bps_static_base64', 86400, function () {
            $logoPath = public_path('images/logo_bps.png');
            if (file_exists($logoPath)) {
                return 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }
            return null;
        });
        foreach ($records as $record) {
            try {
                if (!$record->hash) {
                    $record->update(['hash' => \Illuminate\Support\Str::random(32)]);
                }

                $qrBase64 = null;
                if ($record->status === 'approved') {
                    $verifyUrl = route('surat-tugas.verify', $record->hash);
                    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl);
                    $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
                }

                $periode = \App\Filament\Resources\SuratTugasResource::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('surat-tugas.pdf_table_layout', [
                    'surat' => $record,
                    'logoBase64' => $logoBase64,
                    'qrBase64' => $qrBase64,
                    'periode' => $periode,
                    'is_preview' => false,
                ])->setPaper('a4', 'portrait');

                // NOTE: Encryption is NOT applied here for bulk merge.
                // Ghostscript cannot read encrypted PDFs, so encryption is applied
                // on the final merged PDF via Ghostscript's -sOwnerPassword flag.

                $surveyName = $record->survey ? str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->survey->name) : 'NoSurvey';
                $userName = str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->user->name);
                $nomorSurat = str_replace(['/', '\\'], '_', $record->nomor_surat);
                
                $fileName = "{$nomorSurat}-{$surveyName}-{$userName}.pdf";
                $tempPath = storage_path('app/temp_bulk_pdf_' . uniqid() . '.pdf');
                
                file_put_contents($tempPath, $pdf->output());
                $tempFiles[] = $tempPath;
                
                // Clean up heavy DomPDF objects
                unset($pdf);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Skipped PDF for SuratTugas #{$record->id} ({$record->nomor_surat}): " . $e->getMessage());
                continue;
            }
        }
    }

    protected function notifyUser($title, $body, $status)
    {
        $user = User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->status($status)
                ->sendToDatabase($user);
        }
    }
}
