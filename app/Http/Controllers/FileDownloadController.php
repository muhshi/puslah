<?php

namespace App\Http\Controllers;

use App\Models\LaporanLembur;
use App\Models\LaporanPerjalananDinas;
use App\Services\LpdExportService;
use App\Services\LemburExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileDownloadController extends Controller
{
    /**
     * Download public storage file with guaranteed Content-Disposition attachment.
     */
    public function downloadStorageFile(Request $request): BinaryFileResponse
    {
        $path = $request->query('path');
        $name = $request->query('name');
        $disk = $request->query('disk', 'public');

        if (!$path || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(400, 'Path file tidak valid.');
        }

        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'File tidak ditemukan di server.');
        }

        $fullPath = Storage::disk($disk)->path($path);
        $filename = $name ? basename($name) : basename($path);

        return response()->download($fullPath, $filename);
    }

    /**
     * Download all photos for a Laporan Perjalanan Dinas as ZIP.
     */
    public function downloadLpdPhotosZip(LaporanPerjalananDinas $record, LpdExportService $service): BinaryFileResponse
    {
        $response = $service->downloadPhotosZip($record);
        if (!$response) {
            abort(404, 'Tidak ada foto yang dapat didownload.');
        }
        return $response;
    }

    /**
     * Download all photos for a Laporan Lembur as ZIP.
     */
    public function downloadLemburPhotosZip(LaporanLembur $record, LemburExportService $service): BinaryFileResponse
    {
        $response = $service->downloadPhotosZip($record);
        if (!$response) {
            abort(404, 'Tidak ada foto yang dapat didownload.');
        }
        return $response;
    }
}
