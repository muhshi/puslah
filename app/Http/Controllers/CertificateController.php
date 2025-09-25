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

        $bgBase64 = null;
        if ($template->background_path && Storage::disk('public')->exists($template->background_path)) {
            $bgData = Storage::disk('public')->get($template->background_path);
            $mime = Storage::disk('public')->mimeType($template->background_path) ?? 'image/jpeg';

            // Konversi WEBP -> JPEG (pakai GD)
            if ($mime === 'image/webp') {
                if (function_exists('imagecreatefromstring')) {
                    $im = imagecreatefromstring($bgData);
                    if ($im !== false) {
                        ob_start();
                        imagejpeg($im, null, 85);
                        $bgData = ob_get_clean();
                        imagedestroy($im);
                        $mime = 'image/jpeg';
                    }
                }
            }

            // pastikan mimetype final PNG/JPEG
            if (!in_array($mime, ['image/png', 'image/jpeg'])) {
                $mime = 'image/jpeg';
            }

            $bgBase64 = 'data:' . $mime . ';base64,' . base64_encode($bgData);
        }

        $signBase64 = null;
        if ($template->signer_image_path && Storage::disk('public')->exists($template->signer_image_path)) {
            $signBase64 = 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($template->signer_image_path));
        }

        $qrUrl = route('certificates.verify', ['no' => $certificate->certificate_no]);
        // QR utama (besar)
        $qrPng = QrCode::format('png')->size($template->qr_size)->margin(0)->generate($qrUrl);
        $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);

        // QR kecil untuk area TTD (mis. 120px)
        $signQrSize = 120;
        $signQrPng = QrCode::format('png')->size($signQrSize)->margin(0)->generate($qrUrl);
        $signQrBase64 = 'data:image/png;base64,' . base64_encode($signQrPng);

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
