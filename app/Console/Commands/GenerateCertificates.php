<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateCertificates extends Command
{
    /**
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-certificates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate certificates for completed Surat Tugas automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $this->info("Starting certificate generation check at {$now}...");

        // 1. Find eligible Surat Tugas
        // - Waktu selesai has passed
        // - Status approved
        // - User name is NOT 'terlampir' (case-insensitive)
        $candidates = \App\Models\SuratTugas::with(['user', 'survey'])
            ->where('waktu_selesai', '<', $now)
            ->where('status', 'approved')
            ->whereHas('user', function ($query) {
                $query->whereRaw('LOWER(name) NOT LIKE ?', ['%terlampir%']);
            })
            ->get();

        $this->info("Found {$candidates->count()} candidate Surat Tugas records.");
        $generatedCount = 0;

        foreach ($candidates as $surat) {
            try {
                // Ensure User and Survey exist
                if (!$surat->user || !$surat->survey) {
                    continue;
                }

                // Check if certificate already exists
                $exists = \App\Models\Certificate::where('survey_id', $surat->survey_id)
                    ->where('user_id', $surat->user_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Check active template
                $template = \App\Models\CertificateTemplate::where('active', 1)->first();
                if (!$template) {
                    $this->error("No active certificate template found!");
                    return;
                }

                $this->info("Generating certificate for: {$surat->user->name} (Survey: {$surat->survey->name})");
                $this->issueCertificate($surat->user, $surat->survey, $template);
                $generatedCount++;

                // Also update SurveyUser status to approved if exists
                \App\Models\SurveyUser::where('survey_id', $surat->survey_id)
                    ->where('user_id', $surat->user_id)
                    ->update(['status' => 'approved']);

            } catch (\Exception $e) {
                $this->error("Failed to generate for ID {$surat->id}: " . $e->getMessage());
            }
        }

        $this->info("Done! Generated {$generatedCount} new certificates.");
    }

    /**
     * Logic copied/adapted from ParticipantsRelationManager::issueCertificate
     */
    protected function issueCertificate($user, $survey, $template): void
    {
        $cfg = app(\App\Settings\SystemSettings::class);
        $now = now();
        $y = $now->year;
        $m = str_pad($now->month, 2, '0', STR_PAD_LEFT);

        // Ambil & tingkatkan sequence per tahun
        $seqByYear = $cfg->cert_number_seq_by_year ?? [];
        $next = ($seqByYear[$y] ?? 0) + 1;
        $seqByYear[$y] = $next;
        $cfg->cert_number_seq_by_year = $seqByYear;
        $cfg->save();

        $seq6 = str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $no = "{$cfg->cert_number_prefix}/{$y}/{$m}/{$seq6}";

        // QR (using SVG)
        $verifyUrl = route('certificates.verify', ['no' => $no]);
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(0)->generate($verifyUrl);
        // Ensure directory exists
        if (!\Illuminate\Support\Facades\Storage::exists('certificates/qr')) {
            \Illuminate\Support\Facades\Storage::makeDirectory('certificates/qr');
        }
        $qrPath = "certificates/qr/{$y}{$m}-{$user->id}-{$survey->id}.svg";
        \Illuminate\Support\Facades\Storage::put($qrPath, $qrSvg);

        // Render PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('certificates.pdf', [
            'certificate' => null,
            'template' => $template,
            'user' => $user,
            'survey' => $survey,
            'no' => $no,
            'issuedAt' => $now,
            'signatureDate' => $now,
            'bgBase64' => null,
            'signBase64' => null,
            'qrBase64' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'signQrBase64' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'qrUrl' => $verifyUrl,
            'preview' => false,
        ])->setPaper($template->paper ?? 'a4', $template->orientation ?? 'landscape');

        // Ensure directory exists
        if (!\Illuminate\Support\Facades\Storage::exists('certificates/pdf')) {
            \Illuminate\Support\Facades\Storage::makeDirectory('certificates/pdf');
        }
        $pdfPath = "certificates/pdf/{$y}{$m}-{$user->id}-{$survey->id}.pdf";
        \Illuminate\Support\Facades\Storage::put($pdfPath, $pdf->output());

        // Hash content
        $hash = hash('sha256', \Illuminate\Support\Facades\Storage::get($pdfPath));

        \App\Models\Certificate::create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
            'certificate_no' => $no,
            'issued_at' => $now,
            'file_path' => $pdfPath,
            'hash' => $hash,
        ]);
    }
}
