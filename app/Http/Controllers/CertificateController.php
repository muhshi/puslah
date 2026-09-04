<?php
// app/Http/Controllers/CertificateController.php
namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    private function buildViewData(Certificate $certificate): array
    {
        $template = $certificate->template ?? CertificateTemplate::where('active', true)->firstOrFail();

        $bgBase64 = \App\Services\CertificateImageService::getBase64Image($template->background_path, 'image/jpeg');
        $signBase64 = \App\Services\CertificateImageService::getBase64Image($template->signer_image_path, 'image/png');

        $qrUrl = route('certificates.verify', ['no' => $certificate->certificate_no]);
        // QR utama (SVG agar tidak butuh imagick)
        $qrSvg = QrCode::format('svg')->size($template->qr_size ?? 220)->margin(0)->generate($qrUrl);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        // QR kecil untuk area TTD
        $signQrSize = 120;
        $signQrSvg = QrCode::format('svg')->size($signQrSize)->margin(0)->generate($qrUrl);
        $signQrBase64 = 'data:image/svg+xml;base64,' . base64_encode($signQrSvg);

        return [
            'certificate' => $certificate,
            'template' => $template,
            'user' => $certificate->user,
            'survey' => $certificate->survey,
            'no' => $certificate->certificate_no,
            'issuedAt' => $certificate->issued_at,
            'signatureDate' => $certificate->signature_date ?? $certificate->issued_at,
            'bgBase64' => $bgBase64,
            'signBase64' => $signBase64,
            'qrBase64' => $qrBase64,
            'qrUrl' => $qrUrl,
            'signQrBase64' => $signQrBase64,
        ];
    }

    public function preview(Certificate $certificate)
    {
        $user = Auth::user();
        if (!($user->roles[0]->name === 'super_admin' || $certificate->user_id === $user->id))
            abort(403);

        $data = $this->buildViewData($certificate);
        return view('certificates.preview', $data);
    }

    public function download(Certificate $certificate)
    {
        $user = Auth::user();
        if (!($user->roles[0]->name === 'super_admin' || $certificate->user_id === $user->id))
            abort(403);

        $data = $this->buildViewData($certificate);
        $pdf = Pdf::loadView('certificates.pdf', $data)
            ->setPaper($data['template']->paper, $data['template']->orientation);

        return $pdf->download(Str::slug($certificate->certificate_no) . '.pdf');
    }
}
