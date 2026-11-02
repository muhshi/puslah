<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\User;
use App\Settings\SystemSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table as TableStyle;

class WorkReportGeneratorService
{
    /**
     * Generate berkas Word (.docx) Bukti Dukung Kinerja Pegawai.
     *
     * @param User $user
     * @param Collection<int, DailyActivity>|array<int, DailyActivity> $activities
     * @param string|null $periodLabel
     * @return array{path: string, name: string}
     */
    public function generateWordReport(User $user, $activities, ?string $periodLabel = null): array
    {
        $phpWord = new PhpWord();

        // 1. Setup Document Properties
        $phpWord->getDocInfo()
            ->setCreator('Puslah - BPS Kabupaten Demak')
            ->setTitle('Laporan Bukti Dukung Kinerja Harian')
            ->setSubject('Bukti Dukung Kinerja Pegawai');

        // 2. Setup Page Styles (A4 Landscape untuk tabel bukti dukung yang rapi)
        $sectionStyle = [
            'orientation' => 'landscape',
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ];
        $section = $phpWord->addSection($sectionStyle);

        // 3. Define Typography Styles
        $phpWord->addFontStyle('HeaderTitle', ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => '111827']);
        $phpWord->addFontStyle('HeaderSub', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '374151']);
        $phpWord->addFontStyle('TableHeader', ['name' => 'Arial', 'size' => 9, 'bold' => true, 'color' => 'FFFFFF']);
        $phpWord->addFontStyle('TableBody', ['name' => 'Arial', 'size' => 9, 'color' => '1F2937']);
        $phpWord->addFontStyle('TableBodySmall', ['name' => 'Arial', 'size' => 8, 'italic' => true, 'color' => '4B5563']);
        $phpWord->addFontStyle('SignTitle', ['name' => 'Arial', 'size' => 9, 'bold' => true, 'color' => '111827']);

        $phpWord->addParagraphStyle('Center', ['alignment' => Jc::CENTER, 'spaceAfter' => 60]);
        $phpWord->addParagraphStyle('Left', ['alignment' => Jc::START, 'spaceAfter' => 40]);
        $phpWord->addParagraphStyle('Right', ['alignment' => Jc::END, 'spaceAfter' => 40]);

        $settings = app(SystemSettings::class);
        $instansi = $settings->default_office_name ?? 'BADAN PUSAT STATISTIK KABUPATEN DEMAK';

        // 4. Header / Kop Laporan
        $section->addText(strtoupper($instansi), 'HeaderTitle', 'Center');
        $section->addText('LAPORAN BUKTI DUKUNG KINERJA HARIAN PEGAWAI', 'HeaderTitle', 'Center');
        if ($periodLabel) {
            $section->addText("Periode: {$periodLabel}", 'HeaderSub', 'Center');
        }
        $section->addTextBreak(1);

        // 5. Data Identitas Pegawai
        $profile = $user->profile;
        $namaLengkap = $profile?->full_name ?: $user->name;
        $nip = $profile?->nip ?: '-';
        $jabatan = $profile?->jabatan ?: 'Pelaksana';

        $idTable = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'width' => 100 * 50, 'unit' => 'pct']);
        $idRow1 = $idTable->addRow();
        $idRow1->addCell(2000)->addText('Nama Pegawai', ['bold' => true, 'size' => 9]);
        $idRow1->addCell(5000)->addText(": {$namaLengkap}", ['size' => 9]);

        $idRow2 = $idTable->addRow();
        $idRow2->addCell(2000)->addText('NIP', ['bold' => true, 'size' => 9]);
        $idRow2->addCell(5000)->addText(": {$nip}", ['size' => 9]);

        $idRow3 = $idTable->addRow();
        $idRow3->addCell(2000)->addText('Jabatan / Unit', ['bold' => true, 'size' => 9]);
        $idRow3->addCell(5000)->addText(": {$jabatan}", ['size' => 9]);

        $section->addTextBreak(1);

        // 6. Tabel Utama Bukti Dukung
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '9CA3AF',
            'cellMarginTop' => 80,
            'cellMarginBottom' => 80,
            'cellMarginLeft' => 100,
            'cellMarginRight' => 100,
            'alignment' => JcTable::CENTER,
            'cantSplit' => true,
        ];
        $phpWord->addTableStyle('WorkLogTable', $tableStyle);
        $table = $section->addTable('WorkLogTable');

        // Table Header
        $headerRow = $table->addRow(400, ['tblHeader' => true]);
        $thStyle = ['bgColor' => '1E3A8A', 'valign' => 'center'];
        $headerRow->addCell(600, $thStyle)->addText('NO', 'TableHeader', 'Center');
        $headerRow->addCell(1800, $thStyle)->addText('HARI / TANGGAL', 'TableHeader', 'Center');
        $headerRow->addCell(2000, $thStyle)->addText('SURAT TUGAS / STATUS', 'TableHeader', 'Center');
        $headerRow->addCell(5600, $thStyle)->addText('URAIAN KEGIATAN KINERJA', 'TableHeader', 'Center');
        $headerRow->addCell(3000, $thStyle)->addText('BUKTI DUKUNG DOKUMENTASI', 'TableHeader', 'Center');

        $extraPhotos = [];

        // Data Rows
        $no = 1;
        foreach ($activities as $act) {
            $row = $table->addRow(null, ['cantSplit' => true]);

            // No
            $row->addCell(600, ['valign' => 'top'])->addText($no++, 'TableBody', 'Center');

            // Tanggal
            $tgl = Carbon::parse($act->activity_date)->locale('id')->translatedFormat('l, j F Y');
            $row->addCell(1800, ['valign' => 'top'])->addText($tgl, 'TableBody', 'Left');

            // Surat Tugas / Kategori
            $stText = $act->suratTugas ? "ST No: " . ($act->suratTugas->nomor_surat_tugas ?: 'Draft') : 'Kegiatan Rutin';
            $row->addCell(2000, ['valign' => 'top'])->addText($stText, 'TableBody', 'Left');

            // Uraian
            $cellUraian = $row->addCell(5600, ['valign' => 'top']);
            $cellUraian->addText($act->final_content, 'TableBody', 'Left');

            // Foto
            $cellFoto = $row->addCell(3000, ['valign' => 'top']);
            $attachments = $act->attachments;

            if ($attachments->isNotEmpty()) {
                $firstAtt = $attachments->first();
                $photoPath = storage_path('app/public/' . $firstAtt->file_path);

                if (file_exists($photoPath)) {
                    try {
                        $cellFoto->addImage($photoPath, [
                            'width' => 150,
                            'height' => 105,
                            'ratio' => true,
                            'alignment' => Jc::CENTER,
                        ]);
                        if (!empty($firstAtt->caption)) {
                            $cellFoto->addText($firstAtt->caption, 'TableBodySmall', 'Center');
                        }
                    } catch (\Throwable $e) {
                        $cellFoto->addText('[Foto tidak terbaca]', 'TableBodySmall', 'Center');
                    }
                } else {
                    $cellFoto->addText('[Foto terlampir di sistem]', 'TableBodySmall', 'Center');
                }

                // Jika ada lebih dari 1 foto, kumpulkan ke lampiran tambahan
                if ($attachments->count() > 1) {
                    $cellFoto->addTextBreak(1);
                    $cellFoto->addText("(+" . ($attachments->count() - 1) . " foto di Lampiran)", 'TableBodySmall', 'Center');
                    foreach ($attachments->skip(1) as $extra) {
                        $extraPhotos[] = [
                            'date' => $tgl,
                            'activity' => $act->final_content,
                            'attachment' => $extra,
                        ];
                    }
                }
            } else {
                $cellFoto->addText('-', 'TableBody', 'Center');
            }
        }

        $section->addTextBreak(2);

        // 7. Bagian Pengesahan / Tanda Tangan
        $signTable = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'width' => 100 * 50, 'unit' => 'pct']);
        $signRow = $signTable->addRow();

        $pejabatKota = $settings->signer_city ?? 'Demak';
        $tglCetak = Carbon::now()->locale('id')->translatedFormat('j F Y');

        $cellAtasan = $signRow->addCell(6500, ['valign' => 'top']);
        $cellAtasan->addText('Mengetahui / Menyetujui,', 'SignTitle', 'Left');
        $cellAtasan->addText('Pejabat Penilai / Atasan Langsung', 'TableBody', 'Left');
        $cellAtasan->addTextBreak(3);
        $cellAtasan->addText('_____________________________', 'TableBody', 'Left');
        $cellAtasan->addText('NIP. .................................................', 'TableBodySmall', 'Left');

        $cellPegawai = $signRow->addCell(6500, ['valign' => 'top']);
        $cellPegawai->addText("{$pejabatKota}, {$tglCetak}", 'SignTitle', 'Right');
        $cellPegawai->addText('Pegawai Yang Bersangkutan,', 'TableBody', 'Right');
        $cellPegawai->addTextBreak(3);
        $cellPegawai->addText("{$namaLengkap}", 'SignTitle', 'Right');
        $cellPegawai->addText("NIP. {$nip}", 'TableBodySmall', 'Right');

        // 8. Halaman Lampiran Dokumentasi Tambahan (Jika Ada)
        if (!empty($extraPhotos)) {
            $section->addPageBreak();
            $section->addText('LAMPIRAN DOKUMENTASI BUKTI DUKUNG TAMBAHAN', 'HeaderTitle', 'Center');
            $section->addTextBreak(1);

            $annexTable = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'width' => 100 * 50, 'unit' => 'pct']);
            $chunks = array_chunk($extraPhotos, 2);

            foreach ($chunks as $chunk) {
                $annexRow = $annexTable->addRow();
                foreach ($chunk as $item) {
                    $c = $annexRow->addCell(6500, ['valign' => 'top']);
                    $pPath = storage_path('app/public/' . $item['attachment']->file_path);
                    if (file_exists($pPath)) {
                        try {
                            $c->addImage($pPath, [
                                'width' => 240,
                                'height' => 160,
                                'ratio' => true,
                                'alignment' => Jc::CENTER,
                            ]);
                        } catch (\Throwable $e) {}
                    }
                    $c->addText($item['date'] . " - " . mb_strimwidth($item['activity'], 0, 50, '...'), 'TableBodySmall', 'Center');
                    if (!empty($item['attachment']->caption)) {
                        $c->addText($item['attachment']->caption, 'TableBodySmall', 'Center');
                    }
                    $c->addTextBreak(1);
                }

                // Jika ganjil, tambahkan cell kosong agar tabel tetap seimbang
                if (count($chunk) === 1) {
                    $annexRow->addCell(6500);
                }
            }
        }

        // 9. Simpan ke Storage
        $exportDir = storage_path('app/public/exports/daily-activities');
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $cleanUserName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $user->name);
        $timestamp = Carbon::now()->format('Ymd_His');
        $fileName = "Bukti_Dukung_{$cleanUserName}_{$timestamp}.docx";
        $fullExportPath = "{$exportDir}/{$fileName}";

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($fullExportPath);

        return [
            'path' => $fullExportPath,
            'name' => $fileName,
        ];
    }
}
