<?php

namespace App\Services;

use App\Models\LaporanLembur;
use App\Settings\SystemSettings;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class LemburExportService
{
    /**
     * Memproses dokumen Word Laporan Lembur (Daftar Hadir Lembur) dengan TemplateProcessor.
     *
     * @return array{path: string, name: string}|null
     */
    public function processWordDocument(LaporanLembur $record, bool $isBulk = false): ?array
    {
        $settings = app(SystemSettings::class);
        $templatePath = $settings->laporan_lembur_template_path ?? null;
        $fullTemplatePath = $templatePath ? storage_path('app/public/' . $templatePath) : null;

        $useDefault = false;
        if (!$fullTemplatePath || !file_exists($fullTemplatePath) || str_contains($templatePath, '01KZSSYYMF4VEDS91B1AJQX71D')) {
            $useDefault = true;
        } else {
            // Check if uploaded file contains lembur placeholder
            $zip = new ZipArchive();
            if ($zip->open($fullTemplatePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if (!str_contains($xml, 'pekerjaan')) {
                    $useDefault = true;
                }
            }
        }

        if ($useDefault) {
            $defaultTemplate = resource_path('templates/template_daftar_hadir_lembur.docx');
            if (file_exists($defaultTemplate)) {
                $fullTemplatePath = $defaultTemplate;
            } else {
                Notification::make()
                    ->title('Template Laporan Lembur belum tersedia')
                    ->danger()
                    ->send();
                return null;
            }
        }

        $template = new TemplateProcessor($fullTemplatePath);

        // Format waktu: 'Jumat / 6 Maret 2026' (sesuai format dinas BPS)
        $waktuFormat = $record->waktu ? Carbon::parse($record->waktu)->locale('id')->translatedFormat('l / j F Y') : '-';
        $mulaiFormat = $record->mulai ? Carbon::parse($record->mulai)->format('H.i') . ' WIB' : '-';
        $selesaiFormat = $record->selesai ? Carbon::parse($record->selesai)->format('H.i') . ' WIB' : '-';

        $template->setValue('waktu', $waktuFormat);
        $template->setValue('unit_kerja', $settings->default_office_name ?? 'BPS Kabupaten Demak');

        // Format Nama Pegawai: title case nama depan, pertahankan gelar akademik di belakang koma
        $user = $record->user;
        $profile = $user?->profile;
        $rawName = $profile?->full_name ?? $user?->name ?? '-';
        $nameParts = explode(',', $rawName);
        $nameParts[0] = Str::title($nameParts[0]);
        $namaPegawaiFormatted = implode(',', $nameParts);
        $template->setValue('nama_pegawai', $namaPegawaiFormatted);

        // Variabel profil tambahan
        $template->setValue('nip_pegawai', $profile?->nip ?? '-');
        $template->setValue('jabatan', $profile?->jabatan ?? '-');
        $template->setValue('pangkat_golongan', $profile?->pangkat_golongan ?? '-');

        // Waktu mulai & selesai
        $template->setValue('mulai', $mulaiFormat);
        $template->setValue('selesai', $selesaiFormat);

        // Pejabat Penandatangan (Kepala)
        $template->setValue('jabatan_kepala', $settings->cert_signer_title ?? 'Kepala BPS Kab. Demak');
        $template->setValue('nama_kepala', $settings->cert_signer_name ?? 'Khomarudin, S. ST');
        $template->setValue('nip_kepala', $settings->cert_signer_nip ?? '197512091999011001');

        // Konversi rich text HTML ke OpenXML
        $pekerjaanXml = HtmlToWordXmlConverter::convert($record->pekerjaan ?? '', 'Arial', 20);
        $template->setValue('pekerjaan', $pekerjaanXml);

        // Foto Dokumentasi (Maksimal 4 foto)
        for ($i = 1; $i <= 4; $i++) {
            $fotoField = "foto_{$i}";
            $fotoRelPath = $record->{$fotoField};
            $fullPath = $fotoRelPath ? storage_path('app/public/' . $fotoRelPath) : null;

            if ($fullPath && file_exists($fullPath)) {
                try {
                    $template->setImageValue($fotoField, [
                        'path' => $fullPath,
                        'width' => 280,
                        'height' => 320,
                        'ratio' => true,
                    ]);
                } catch (\Exception $e) {
                    $template->setValue($fotoField, '[Error format foto]');
                }
            } else {
                $template->setValue($fotoField, '');
            }
        }

        $waktuFile = $record->waktu ? Carbon::parse($record->waktu)->format('Y_m_d') : now()->format('Y_m_d');
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $user?->name ?? 'Pegawai');
        $namaFile = "Laporan_Lembur_{$safeName}_{$waktuFile}.docx";
        $prefix = $isBulk ? 'temp_bulk_lembur_' : 'temp_lembur_';
        $tempPath = storage_path("app/{$prefix}" . $namaFile);
        $template->saveAs($tempPath);

        return ['path' => $tempPath, 'name' => $namaFile];
    }

    /**
     * Download dokumen Word single Laporan Lembur.
     */
    public function downloadWord(LaporanLembur $record): ?BinaryFileResponse
    {
        $file = $this->processWordDocument($record, false);
        if (!$file) {
            return null;
        }

        return response()->download($file['path'], $file['name'])->deleteFileAfterSend();
    }

    /**
     * Download massal dokumen Laporan Lembur sebagai ZIP.
     */
    public function downloadBulkZip(Collection $records): ?BinaryFileResponse
    {
        $zipFileName = 'Laporan_Lembur_Bulk_' . now()->format('YmdHis') . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Notification::make()
                ->title('Gagal membuat file ZIP')
                ->danger()
                ->send();
            return null;
        }

        $tempFiles = [];
        foreach ($records as $record) {
            $file = $this->processWordDocument($record, true);
            if ($file) {
                $zip->addFile($file['path'], $file['name']);
                $tempFiles[] = $file['path'];
            }
        }
        $zip->close();

        foreach ($tempFiles as $tempPath) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return response()->download($zipPath)->deleteFileAfterSend();
    }
}
