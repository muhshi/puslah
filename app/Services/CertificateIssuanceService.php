<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\SurveyUser;
use App\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateIssuanceService
{
    /**
     * Menerbitkan sertifikat untuk peserta survei.
     *
     * @throws Exception
     */
    public function issue(SurveyUser $row): Certificate
    {
        // 1. Cegah penerbitan ganda
        $existing = Certificate::where('survey_id', $row->survey_id)
            ->where('user_id', $row->user_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // 2. Ambil template sertifikat yang aktif
        $template = CertificateTemplate::where('active', 1)->first();
        if (!$template) {
            throw new Exception('Tidak ada template sertifikat yang aktif.');
        }

        $cfg = app(SystemSettings::class);
        $now = now();
        $year = $now->year;
        $month = str_pad((string) $now->month, 2, '0', STR_PAD_LEFT);

        // 3. Ambil & tingkatkan sequence per tahun secara aman
        $seqByYear = $cfg->cert_number_seq_by_year ?? [];
        $next = ($seqByYear[$year] ?? 0) + 1;
        $seqByYear[$year] = $next;
        $cfg->cert_number_seq_by_year = $seqByYear;
        $cfg->save();

        $seq6 = str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $no = "{$cfg->cert_number_prefix}/{$year}/{$month}/{$seq6}";

        // 4. Generate QR code SVG (menghindari ketergantungan PHP-Imagick)
        $verifyUrl = route('certificates.verify', ['no' => $no]);
        $qrSvg = QrCode::format('svg')->size(220)->margin(0)->generate($verifyUrl);
        $qrPath = "certificates/qr/{$year}{$month}-{$row->user_id}-{$row->survey_id}.svg";
        Storage::put($qrPath, $qrSvg);

        // 5. Render PDF sertifikat
        $user = $row->user;
        $survey = $row->survey;
        $pdf = Pdf::loadView('certificates.pdf', [
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

        $pdfPath = "certificates/pdf/{$year}{$month}-{$row->user_id}-{$row->survey_id}.pdf";
        Storage::put($pdfPath, $pdf->output());

        $hash = hash('sha256', Storage::get($pdfPath));

        return Certificate::create([
            'survey_id' => $row->survey_id,
            'user_id' => $row->user_id,
            'certificate_no' => $no,
            'issued_at' => $now,
            'file_path' => $pdfPath,
            'hash' => $hash,
        ]);
    }
}
