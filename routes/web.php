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
