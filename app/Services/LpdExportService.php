<?php

namespace App\Services;

use App\Models\LaporanPerjalananDinas;
use App\Settings\SystemSettings;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class LpdExportService
{
    /**
     * Memproses dokumen Word Laporan Perjalanan Dinas (.docx) dengan TemplateProcessor.
     *
     * @return array{path: string, name: string}|null
     */
    public function processWordDocument(LaporanPerjalananDinas $record, bool $isBulk = false): ?array
    {
        $settings = app(SystemSettings::class);
        $templatePath = $settings->laporan_dinas_template_path;

        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
            Notification::make()
                ->title('Template Laporan Dinas belum diupload di Pengaturan Sistem')
                ->danger()
                ->send();
            return null;
        }

        $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));

        $st = $record->suratTugas;
        $user = $st?->user;
        $profile = $user?->profile;

        $nameParts = explode(',', $user?->name ?? 'Pegawai');
        $nameParts[0] = Str::title($nameParts[0]);
        $namaPegawaiFormatted = implode(',', $nameParts);

        $template->setValue('nama_pegawai', $namaPegawaiFormatted);
        $template->setValue('nomor_surat_tugas', $record->nomor_surat_tugas);
        $template->setValue('tujuan', $record->tujuan);
        $template->setValue('tanggal_kunjungan', $record->tanggal_kunjungan ? $record->tanggal_kunjungan->translatedFormat('d F Y') : '-');

        // Variabel Profil & Surat Pernyataan
        $template->setValue('nip_pegawai', $profile?->nip ?? '-');
        $template->setValue('pangkat_golongan', $profile?->pangkat_golongan ?? '-');
        $template->setValue('jabatan', $profile?->jabatan ?? '-');
        $template->setValue('unit_kerja', $settings->default_office_name ?? 'BPS Kabupaten Demak');
        $template->setValue('tanggal_pernyataan', $record->tanggal_kunjungan ? $record->tanggal_kunjungan->translatedFormat('d F Y') : '-');

        // Konversi HTML RichEditor ke OpenXML Word
        $uraianXml = HtmlToWordXmlConverter::convert($record->uraian_kegiatan ?? '', 'Aptos Display', 24);
        $template->setValue('uraian_kegiatan', $uraianXml);

        $template->setValue('nama_pejabat', $record->nama_pejabat ?? '-');
        $template->setValue('desa_pejabat', $record->desa_pejabat ?? '-');

        // Foto Dokumentasi (Maksimal 10 foto)
        $fotos = $record->fotos()->orderBy('urutan')->take(10)->get();
        $photoCount = $fotos->count();

        // Dynamic sizing logic "Fit to Page"
        $targetWidth = 600;
        $targetHeight = 800;

        if ($photoCount <= 1) {
            $targetWidth = 600;
            $targetHeight = 600;
        } elseif ($photoCount <= 2) {
            $targetWidth = 600;
            $targetHeight = 400;
        } elseif ($photoCount <= 4) {
            $targetWidth = 250;
            $targetHeight = 300;
        } else {
            $targetWidth = 250;
            $targetHeight = 200;
        }

        for ($i = 1; $i <= 10; $i++) {
            $foto = $fotos->get($i - 1);
            if ($foto && Storage::exists('public/' . $foto->file_path)) {
                try {
                    $template->setImageValue("foto_{$i}", [
                        'path' => storage_path('app/public/' . $foto->file_path),
                        'width' => $targetWidth,
                        'height' => $targetHeight,
                        'ratio' => true,
                    ]);
                    $template->setValue("keterangan_foto_{$i}", $foto->keterangan ?? '');
                } catch (\Exception $e) {
                    $template->setValue("foto_{$i}", '[Error format foto]');
                    $template->setValue("keterangan_foto_{$i}", '');
                }
            } else {
                $template->setValue("foto_{$i}", '');
                $template->setValue("keterangan_foto_{$i}", '');
            }
        }

        // Simpan file sementara
        $namaPegawai = $user?->name ?? 'Pegawai';
        $namaSurvey = $st?->survey ? $st->survey->name : 'Survey';
        $tanggal = $record->tanggal_kunjungan ? $record->tanggal_kunjungan->format('Y_m_d') : now()->format('Y_m_d');

        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $namaPegawai);
        $safeSurvey = preg_replace('/[^a-zA-Z0-9]/', '_', $namaSurvey);

        $fileName = "{$safeName}_{$safeSurvey}_{$tanggal}.docx";
        $prefix = $isBulk ? 'temp_bulk_laporan_' : 'temp_laporan_';
        $tempPath = storage_path("app/{$prefix}" . $fileName);
        $template->saveAs($tempPath);

        return ['path' => $tempPath, 'name' => $fileName];
    }

    /**
     * Download dokumen Word single LPD.
     */
    public function downloadWord(LaporanPerjalananDinas $record): ?BinaryFileResponse
    {
        $file = $this->processWordDocument($record, false);
        if (!$file) {
            return null;
        }

        return response()->download($file['path'], $file['name'])->deleteFileAfterSend();
    }

    /**
     * Download massal dokumen LPD sebagai ZIP.
     */
    public function downloadBulkZip(Collection $records): ?BinaryFileResponse
    {
        $zipFileName = 'Laporan_Dinas_Bulk_' . now()->format('YmdHis') . '.zip';
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

        // Bersihkan temp files
        foreach ($tempFiles as $tempPath) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return response()->download($zipPath)->deleteFileAfterSend();
    }
}
