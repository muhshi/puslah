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
        
        $zipFileName = 'Surat_Tugas_' . strtoupper($this->type) . '_Bulk_' . now()->format('YmdHis') . '.zip';
        $zipPath = Storage::disk('public')->path('exports/' . $zipFileName);
        $zipUrl = asset('storage/exports/' . $zipFileName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->notifyUser('Gagal', 'Sistem gagal membuat file ZIP.', 'danger');
            return;
        }

        $tempFiles = [];
        $totalProcessed = 0;

        // Process in chunks of 50 to prevent RAM exhaustion
        $chunks = array_chunk($this->recordIds, 50);

        foreach ($chunks as $chunkIds) {
            $records = SuratTugas::whereIn('id', $chunkIds)->with(['survey', 'user.profile'])->get();
            
            if ($this->type === 'pdf') {
                $this->processPdf($records, $zip, $tempFiles);
            } else {
                $this->processWord($records, $zip, $tempFiles);
            }

            $totalProcessed += $records->count();

            // Clear memory after each chunk
            unset($records);
            gc_collect_cycles();
        }

        $zip->close();

        // Clean up temp files
        foreach ($tempFiles as $tempPath) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        // Send Success Notification with Download Button
        $user = User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('File ZIP Surat Tugas Siap!')
                ->body('Proses kompresi ' . $totalProcessed . ' file ' . strtoupper($this->type) . ' telah selesai.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Download ZIP')
                        ->button()
                        ->url($zipUrl)
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($user);
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

    protected function processPdf($records, &$zip, &$tempFiles)
    {
        $logoBase64 = \Illuminate\Support\Facades\Cache::remember('logo_bps_static_base64', 86400, function () {
            $logoPath = public_path('images/logo_bps.png');
            if (file_exists($logoPath)) {
                return 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }
            return null;
        });

        $settings = app(SystemSettings::class);
        $masterPassword = $settings->pdf_master_password;

        foreach ($records as $record) {
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

            if (!empty($masterPassword)) {
                $pdf->setEncryption('', $masterPassword, ['print']);
            }

            $surveyName = $record->survey ? str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->survey->name) : 'NoSurvey';
            $userName = str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->user->name);
            $nomorSurat = str_replace(['/', '\\'], '_', $record->nomor_surat);
            
            $fileName = "{$nomorSurat}-{$surveyName}-{$userName}.pdf";
            $tempPath = storage_path('app/temp_bulk_pdf_' . uniqid() . '.pdf');
            
            file_put_contents($tempPath, $pdf->output());
            $tempFiles[] = $tempPath;
            $zip->addFile($tempPath, $fileName);
            
            // Clean up heavy DomPDF objects
            unset($pdf);
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
