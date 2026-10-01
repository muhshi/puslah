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

        if (!$path || str_contains($path, '..')) {
            abort(400, 'Path file tidak valid.');
        }

        // Normalisasi path: decode URL, hilangkan slash awal dan prefix umum
        $cleanPath = ltrim(urldecode($path), '/\\');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }
        if (str_starts_with($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        $cleanPath = ltrim($cleanPath, '/\\');

        $fullPath = null;
        if (Storage::disk($disk)->exists($cleanPath)) {
            $fullPath = Storage::disk($disk)->path($cleanPath);
        } elseif (file_exists(storage_path('app/public/' . $cleanPath))) {
            $fullPath = storage_path('app/public/' . $cleanPath);
        } elseif (file_exists(public_path('storage/' . $cleanPath))) {
            $fullPath = public_path('storage/' . $cleanPath);
        } elseif (file_exists(storage_path('app/' . $cleanPath))) {
            $fullPath = storage_path('app/' . $cleanPath);
        } elseif (file_exists(public_path($cleanPath))) {
            $fullPath = public_path($cleanPath);
        }

        if (!$fullPath || !file_exists($fullPath)) {
            abort(404, 'File tidak ditemukan di server.');
        }

        $filename = $name ? basename($name) : basename($cleanPath);

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
