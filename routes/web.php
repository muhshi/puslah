<?php

use App\Livewire\Presensi;
use App\Models\Certificate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return redirect('admin/login');
})->name('login');

Route::get('/admin/auth/google', [\App\Http\Controllers\SocialiteController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/admin/auth/google/callback', [\App\Http\Controllers\SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');

Route::group(['middleware' => 'auth'], function () {
    Route::get('presensi', Presensi::class)->name('presensi');
});

Route::get('/certificates/{certificate}/preview', [\App\Http\Controllers\CertificateController::class, 'preview'])
    ->name('certificates.preview')
    ->middleware(['auth']);

Route::get('/certificates/{certificate}/download', [\App\Http\Controllers\CertificateController::class, 'download'])
    ->name('certificates.download')
    ->middleware(['auth']);

// Route::get('/certificates/{certificate}/download', function (Certificate $certificate) {
//     $user = Auth::user();
//     if (!($user->roles[0]->name == 'super_admin' || $certificate->user_id === $user->id)) {
//         abort(403);
//     }

//     abort_if(!Storage::exists($certificate->file_path), 404);
//     return response()->file(storage_path('app/' . $certificate->file_path));
// })->name('certificates.download')->middleware(['auth']);


Route::get('/verify', function () {
    $no = request('no');
    $cert = Certificate::with(['user', 'survey'])->where('certificate_no', $no)->first();
    if (!$cert || $cert->revoked) {
        return view('certificates.verify', ['ok' => false, 'no' => $no]);
    }
    return view('certificates.verify', ['ok' => true, 'cert' => $cert]);
})->name('certificates.verify');

Route::get('/surat-tugas/verify/{hash}', function ($hash) {
    $surat = \App\Models\SuratTugas::with('user.profile', 'survey')->where('hash', $hash)->first();
    if (!$surat) {
        return view('surat-tugas.verify', ['ok' => false]);
    }
    return view('surat-tugas.verify', ['ok' => true, 'surat' => $surat]);
})->name('surat-tugas.verify');

// Stream PDF inline for verified surat tugas
Route::get('/surat-tugas/verify/{hash}/pdf', function ($hash) {
    $surat = \App\Models\SuratTugas::with('user.profile', 'survey')->where('hash', $hash)->first();
    if (!$surat) {
        abort(404);
    }

    // Logo
    $logoBase64 = \Illuminate\Support\Facades\Cache::remember('logo_bps_static_base64', 86400, function () {
        $logoPath = public_path('images/logo_bps.png');
        if (file_exists($logoPath)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
        return null;
    });

    // QR
    $verifyUrl = route('surat-tugas.verify', $surat->hash);
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl);
    $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

    // Periode
    $periode = \App\Filament\Resources\SuratTugasResource::formatPeriodeTugas($surat->waktu_mulai, $surat->waktu_selesai);

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('surat-tugas.pdf_table_layout', [
        'surat' => $surat,
        'logoBase64' => $logoBase64,
        'qrBase64' => $qrBase64,
        'periode' => $periode,
        'is_preview' => false,
    ])->setPaper('a4', 'portrait');

    // Apply encryption if master password is set
    $settings = app(\App\Settings\SystemSettings::class);
    if (!empty($settings->pdf_master_password)) {
        $pdf->setEncryption(null, $settings->pdf_master_password, ['print']);
    }

    return $pdf->stream('Surat_Tugas_' . str_replace(['/', '\\'], '_', $surat->nomor_surat) . '.pdf');
})->name('surat-tugas.verify.pdf');

// Preview route for layout testing (development only)
Route::get('/surat-tugas/preview/{id?}', function ($id = null) {
    if ($id) {
        $surat = \App\Models\SuratTugas::with('user.profile', 'survey')->findOrFail($id);
    } else {
        // Use the latest surat tugas if no ID specified
        $surat = \App\Models\SuratTugas::with('user.profile', 'survey')->latest()->first();
        if (!$surat) {
            return 'No Surat Tugas found. Create one first.';
        }
    }

    // Generate hash if not exists
    if (!$surat->hash) {
        $surat->update(['hash' => \Illuminate\Support\Str::random(32)]);
    }

    // Cache logo
    $logoBase64 = \Illuminate\Support\Facades\Cache::remember('logo_bps_static_base64', 86400, function () {
        $logoPath = public_path('images/logo_bps.png');
        if (file_exists($logoPath)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
        return null;
    });

    // Generate QR
    $verifyUrl = route('surat-tugas.verify', $surat->hash);
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl);
    $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

    // Format periode
    $periode = \App\Filament\Resources\SuratTugasResource::formatPeriodeTugas($surat->waktu_mulai, $surat->waktu_selesai);

    //return view('surat-tugas.pdf', [
    return view('surat-tugas.pdf_table_layout', [
        'surat' => $surat,
        'logoBase64' => $logoBase64,
        'qrBase64' => $qrBase64,
        'periode' => $periode,
        'is_preview' => true,
    ]);
})->name('surat-tugas.preview');

Route::get('/templates/download/employee', function () {
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\EmployeeTemplateExport, 'employee_import_template.xlsx');
})->name('download.employee.template');
