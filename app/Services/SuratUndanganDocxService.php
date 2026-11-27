<?php

namespace App\Services;

use App\Models\SuratUndangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\VerticalJc;

class SuratUndanganDocxService
{
    protected SuratUndanganPdfService $pdfService;

    public function __construct(SuratUndanganPdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Generate PhpWord instance untuk Surat Undangan.
     */
    public function generate(SuratUndangan $suratUndangan): PhpWord
    {
        $suratUndangan->loadMissing(['kopSurat', 'survey', 'schedules']);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        // Section Style (Folio / F4, Margin 2cm, header halaman 1 berbeda)
        $sectionStyle = [
            'paperSize'    => 'Folio',
            'marginTop'    => 1700,
            'marginBottom' => 1134,
            'marginLeft'   => 1134,
            'marginRight'  => 1134,
            'headerHeight' => 450,
            'differentFirstPageHeaderFooter' => true,
        ];
        $section = $phpWord->addSection($sectionStyle);

        // 1. KOP SURAT (di Header Halaman 1: hanya logo BPS lebar + logo kanan + borderline)
        $this->addKopSurat($section, $suratUndangan);

        // 2. META HEADER (Nomor, Sifat, Lampiran, Hal, Tanggal)
        $this->addMetaHeader($section, $suratUndangan);

        // 3. TUJUAN (Yth. ...)
        $this->addTujuan($section, $suratUndangan);

        // 4. ISI SURAT (Pembuka + HTML Content + Penutup)
        $this->addIsiSurat($section, $suratUndangan);

        // 5. TANDA TANGAN (Kanan bawah, tanpa BSrE)
        $this->addTandaTangan($section, $suratUndangan);

        // 6. LAMPIRAN 1: DAFTAR PESERTA
        if ($suratUndangan->sertakan_lampiran_peserta) {
            $section->addPageBreak();
            $this->addLampiranPeserta($section, $suratUndangan);
        }

        // 7. LAMPIRAN 2: JADWAL KEGIATAN
        if ($suratUndangan->sertakan_lampiran_jadwal) {
            $section->addPageBreak();
            $this->addLampiranJadwal($section, $suratUndangan);
        }

        return $phpWord;
    }

    protected function addKopSurat($section, SuratUndangan $suratUndangan): void
    {
        $kopSurat = $suratUndangan->kopSurat;
        $logoKiri = $this->resolveImagePath($kopSurat?->logo_kiri_path ?: 'images/kop/logo_bps_header.png');
        $logoKanan = $kopSurat?->logo_kanan_path ? $this->resolveImagePath($kopSurat->logo_kanan_path) : null;

        // Taruh Kop Surat di Header Halaman Pertama (sesuai template kop berakhlak.docx / kop se26.docx)
        $header = $section->addHeader('first');

        $kopTable = $header->addTable(['alignment' => JcTable::CENTER, 'width' => 100 * 50]);
        $kopTable->addRow();

        // Logo BPS Lebar di Kiri (sudah memuat nama instansi & alamat di dalam gambar)
        $cLeft = $kopTable->addCell(7500, ['valign' => VerticalJc::CENTER]);
        if ($logoKiri && file_exists($logoKiri)) {
            $cLeft->addImage($logoKiri, ['width' => 360, 'height' => 63, 'alignment' => Jc::START]);
        }

        // Logo Pendamping di Kanan (BerAKHLAK / SE2026)
        $cRight = $kopTable->addCell(2500, ['valign' => VerticalJc::CENTER]);
        if ($logoKanan && file_exists($logoKanan)) {
            $cRight->addImage($logoKanan, ['width' => 115, 'height' => 44, 'alignment' => Jc::END]);
        }

        // Garis Pembatas Kop (Borderline)
        $header->addLine([
            'weight' => 2,
            'width'  => 495,
            'height' => 0,
            'color'  => '000000',
        ]);
    }

    protected function addMetaHeader($section, SuratUndangan $suratUndangan): void
    {
        $table = $section->addTable(['alignment' => JcTable::CENTER, 'width' => 100 * 50]);
        $table->addRow();

        $cLeft = $table->addCell(6200, ['valign' => VerticalJc::TOP]);
        $innerTable = $cLeft->addTable();

        $innerTable->addRow();
        $innerTable->addCell(1200)->addText('Nomor');
        $innerTable->addCell(200)->addText(':');
        $innerTable->addCell(4800)->addText($suratUndangan->nomor_surat);

        $innerTable->addRow();
        $innerTable->addCell(1200)->addText('Sifat');
        $innerTable->addCell(200)->addText(':');
        $innerTable->addCell(4800)->addText($suratUndangan->sifat);

        $innerTable->addRow();
        $innerTable->addCell(1200)->addText('Lampiran');
        $innerTable->addCell(200)->addText(':');
        $innerTable->addCell(4800)->addText($suratUndangan->lampiran_teks);

        $innerTable->addRow();
        $innerTable->addCell(1200)->addText('Hal');
        $innerTable->addCell(200)->addText(':');
        $innerTable->addCell(4800)->addText($suratUndangan->hal);

        $cRight = $table->addCell(3800, ['valign' => VerticalJc::TOP]);
        $city = $suratUndangan->signer_city ?: 'Demak';
        $cRight->addText($city . ', ' . $suratUndangan->formatted_tanggal, [], ['alignment' => Jc::END]);

        $section->addTextBreak(1);
    }

    protected function addTujuan($section, SuratUndangan $suratUndangan): void
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $suratUndangan->yth_teks)), fn ($l) => $l !== ''));
        foreach ($lines as $idx => $line) {
            $isTempat = $idx === count($lines) - 1 && strcasecmp($line, 'tempat') === 0;
            $displayLine = $isTempat ? 'Tempat' : $line;
            $indentLeft = $idx === 0 ? 0 : ($isTempat ? 780 : 450);

            $section->addText($displayLine, [], [
                'spaceAfter'  => 20,
                'indentation' => ['left' => $indentLeft],
            ]);
        }
        $section->addTextBreak(1);
    }

    protected function addIsiSurat($section, SuratUndangan $suratUndangan): void
    {
        // Pembuka (indentasi baris pertama 1,25 cm = 709 twips)
        if (!empty($suratUndangan->paragraf_pembuka)) {
            $section->addText($suratUndangan->paragraf_pembuka, [], [
                'alignment'   => Jc::BOTH,
                'spaceAfter'  => 80,
                'indentation' => ['firstLine' => 709],
            ]);
        }

        // Isi Konten HTML (Rich Text Editor)
        if (!empty($suratUndangan->isi_surat_html)) {
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $suratUndangan->isi_surat_html, false, false);
        }

        // Penutup (EYD/PUEBI: indentasi baris pertama + tanda koma baku)
        $section->addText(
            'Demikian disampaikan untuk dapat dipedomani dan dilaksanakan. Atas perhatian Saudara, diucapkan terima kasih.',
            [],
            [
                'alignment'   => Jc::BOTH,
                'spaceBefore' => 120,
                'spaceAfter'  => 120,
                'indentation' => ['firstLine' => 709],
            ]
        );
    }

    protected function addTandaTangan($section, SuratUndangan $suratUndangan): void
    {
        $ttdTable = $section->addTable(['alignment' => JcTable::CENTER, 'width' => 100 * 50]);
        $ttdTable->addRow();
        $ttdTable->addCell(5500); // spacer
        $cTtd = $ttdTable->addCell(4500, ['valign' => VerticalJc::TOP]);
        $cTtd->addText('Kepala Badan Pusat Statistik', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $cTtd->addText('Kabupaten Demak,', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $cTtd->addTextBreak(3);
        $cTtd->addText($suratUndangan->signer_name ?: 'Kepala BPS Demak', [], ['alignment' => Jc::CENTER]);
    }

    protected function addLampiranHeader($section, string $title, SuratUndangan $suratUndangan): void
    {
        $wrapper = $section->addTable(['alignment' => JcTable::CENTER, 'width' => 100 * 50]);
        $wrapper->addRow();
        $wrapper->addCell(4800); // spacer kiri
        $c = $wrapper->addCell(5100, ['valign' => VerticalJc::TOP]);

        $c->addText($title, ['bold' => true], ['spaceAfter' => 20]);
        $c->addText('Surat Kepala BPS Kabupaten Demak', [], ['spaceAfter' => 20]);

        $meta = $c->addTable();
        $meta->addRow();
        $meta->addCell(950)->addText('Nomor', [], ['spaceAfter' => 20]);
        $meta->addCell(180)->addText(':', [], ['spaceAfter' => 20]);
        $meta->addCell(3970)->addText($suratUndangan->nomor_surat, [], ['spaceAfter' => 20]);

        $meta->addRow();
        $meta->addCell(950)->addText('Tanggal', [], ['spaceAfter' => 20]);
        $meta->addCell(180)->addText(':', [], ['spaceAfter' => 20]);
        $meta->addCell(3970)->addText($suratUndangan->formatted_tanggal, [], ['spaceAfter' => 20]);

        $section->addTextBreak(2);
    }

    protected function addLampiranPeserta($section, SuratUndangan $suratUndangan): void
    {
        $this->addLampiranHeader($section, 'Lampiran 1', $suratUndangan);

        // Judul Lampiran
        $kegiatan = strtoupper($suratUndangan->survey->name ?? $suratUndangan->hal);
        $tahun = $suratUndangan->tanggal_surat ? $suratUndangan->tanggal_surat->format('Y') : date('Y');
        $section->addText("DAFTAR PESERTA {$kegiatan}", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText("KABUPATEN DEMAK TAHUN {$tahun}", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);

        $pesertaPerKelas = $this->pdfService->resolvePesertaData($suratUndangan);
        $isMultiClass = count($pesertaPerKelas) > 1 || (count($pesertaPerKelas) === 1 && !isset($pesertaPerKelas['Peserta']));
        $cols = $suratUndangan->kolom_lampiran_peserta ?? ['asal_peserta'];

        // Build active columns with weights so total width strictly fits 9900 twips (Folio width minus margins)
        $optionalDefs = [
            'nip'            => ['label' => 'NIP', 'key' => 'nip', 'weight' => 22, 'align' => Jc::CENTER],
            'asal_peserta'   => ['label' => 'Asal', 'key' => 'asal_peserta', 'weight' => 20, 'align' => Jc::START],
            'asal_kecamatan' => ['label' => 'Asal Kecamatan / Alamat', 'key' => 'asal_kecamatan', 'weight' => 22, 'align' => Jc::START],
            'sobat_id'       => ['label' => 'Sobat ID', 'key' => 'sobat_id', 'weight' => 16, 'align' => Jc::CENTER],
            'email'          => ['label' => 'Email', 'key' => 'email', 'weight' => 24, 'align' => Jc::START],
            'no_hp'          => ['label' => 'No HP / Kontak', 'key' => 'no_hp', 'weight' => 18, 'align' => Jc::CENTER],
        ];

        $activeExtras = [];
        foreach ($optionalDefs as $colKey => $def) {
            if (in_array($colKey, $cols, true)) {
                $activeExtras[] = $def;
            }
        }
        $activeExtras = array_slice($activeExtras, 0, 2);

        $noWidth = 550;
        $remainingWidth = 9900 - $noWidth;
        $baseColumns = [
            ['label' => 'Nama Peserta', 'key' => 'nama', 'weight' => 34, 'align' => Jc::START, 'bold' => true],
            ['label' => 'Bertugas Sebagai', 'key' => 'jabatan', 'weight' => 22, 'align' => Jc::CENTER, 'bold' => false],
        ];
        foreach ($activeExtras as $extra) {
            $baseColumns[] = $extra + ['bold' => false];
        }

        $totalWeight = array_sum(array_column($baseColumns, 'weight'));
        $allocated = 0;
        foreach ($baseColumns as $idx => &$colDef) {
            if ($idx === count($baseColumns) - 1) {
                $colDef['width'] = $remainingWidth - $allocated;
            } else {
                $colDef['width'] = (int) round(($colDef['weight'] / $totalWeight) * $remainingWidth);
                $allocated += $colDef['width'];
            }
        }
        unset($colDef);

        $colCount = 1 + count($baseColumns);

        $tableStyle = [
            'borderSize'  => 6,
            'borderColor' => '000000',
            'alignment'   => JcTable::CENTER,
            'layout'      => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,
        ];
        $headerStyle = ['bgColor' => 'F2F2F2'];

        foreach ($pesertaPerKelas as $namaKelas => $pesertaList) {
            $table = $section->addTable($tableStyle);

            if ($isMultiClass) {
                $table->addRow();
                $table->addCell(9900, ['gridSpan' => $colCount, 'bgColor' => 'E6E6E6'])
                    ->addText('KELAS: ' . strtoupper($namaKelas), ['bold' => true], ['alignment' => Jc::START]);
            }

            // Header Tabel
            $table->addRow();
            $table->addCell($noWidth, $headerStyle)->addText('No', ['bold' => true], ['alignment' => Jc::CENTER]);
            foreach ($baseColumns as $colDef) {
                $table->addCell($colDef['width'], $headerStyle)->addText($colDef['label'], ['bold' => true], ['alignment' => Jc::CENTER]);
            }

            // Body Tabel
            foreach ($pesertaList as $idx => $p) {
                $table->addRow();
                $table->addCell($noWidth)->addText((string) ($idx + 1), [], ['alignment' => Jc::CENTER]);
                foreach ($baseColumns as $colDef) {
                    $val = $p[$colDef['key']] ?? '-';
                    $fontStyle = !empty($colDef['bold']) ? ['bold' => true] : [];
                    $table->addCell($colDef['width'])->addText($val ?: '-', $fontStyle, ['alignment' => $colDef['align']]);
                }
            }

            $section->addTextBreak(1);
        }
    }

    protected function addLampiranJadwal($section, SuratUndangan $suratUndangan): void
    {
        $this->addLampiranHeader($section, 'Lampiran 2', $suratUndangan);

        // Judul Lampiran
        $kegiatan = strtoupper($suratUndangan->survey->name ?? $suratUndangan->hal);
        $tahun = $suratUndangan->tanggal_surat ? $suratUndangan->tanggal_surat->format('Y') : date('Y');
        $section->addText("JADWAL {$kegiatan}", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText("KABUPATEN DEMAK TAHUN {$tahun}", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);

        if ($suratUndangan->mode_jadwal === 'upload' && $suratUndangan->jadwal_image_path) {
            $imgPath = $this->resolveImagePath($suratUndangan->jadwal_image_path);
            if ($imgPath && file_exists($imgPath)) {
                $section->addImage($imgPath, ['width' => 480, 'alignment' => Jc::CENTER]);
            }
        } else {
            $schedulesByDay = $suratUndangan->schedules->groupBy(function ($s) {
                $tgl = $s->tanggal ? Carbon::parse($s->tanggal)->locale('id')->translatedFormat('d F Y') : '-';
                return ($s->hari ? $s->hari . ', ' : '') . $tgl;
            });

            $tableStyle = [
                'borderSize'  => 6,
                'borderColor' => '000000',
                'alignment'   => JcTable::CENTER,
                'layout'      => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,
            ];
            $headerStyle = ['bgColor' => 'F2F2F2'];
            $showDurasi = (bool) $suratUndangan->tampilkan_durasi_jadwal;

            $wNo = 550;
            $wWaktu = $showDurasi ? 1650 : 1750;
            $wDurasi = 950;
            $wMateri = $showDurasi ? 3850 : 4550;
            $wJp = 650;
            $wKet = $showDurasi ? 2250 : 2400;

            foreach ($schedulesByDay as $dayLabel => $items) {
                $table = $section->addTable($tableStyle);

                // Group Day
                $gridSpan = $showDurasi ? 6 : 5;
                $table->addRow();
                $table->addCell(9900, ['gridSpan' => $gridSpan, 'bgColor' => 'E6E6E6'])
                    ->addText('HARI / TANGGAL: ' . strtoupper($dayLabel), ['bold' => true], ['alignment' => Jc::START]);

                // Header
                $table->addRow();
                $table->addCell($wNo, $headerStyle)->addText('No', ['bold' => true], ['alignment' => Jc::CENTER]);
                $table->addCell($wWaktu, $headerStyle)->addText('Waktu (WIB)', ['bold' => true], ['alignment' => Jc::CENTER]);
                if ($showDurasi) {
                    $table->addCell($wDurasi, $headerStyle)->addText('Durasi', ['bold' => true], ['alignment' => Jc::CENTER]);
                }
                $table->addCell($wMateri, $headerStyle)->addText('Materi / Aktivitas', ['bold' => true], ['alignment' => Jc::CENTER]);
                $table->addCell($wJp, $headerStyle)->addText('JP', ['bold' => true], ['alignment' => Jc::CENTER]);
                $table->addCell($wKet, $headerStyle)->addText('Metode / Ket.', ['bold' => true], ['alignment' => Jc::CENTER]);

                // Rows
                foreach ($items as $idx => $item) {
                    $table->addRow();
                    $table->addCell($wNo)->addText((string)($idx + 1), [], ['alignment' => Jc::CENTER]);
                    $waktuStr = ($item->waktu_mulai ?: '') . ' - ' . ($item->waktu_selesai ?: '');
                    $table->addCell($wWaktu)->addText($waktuStr, [], ['alignment' => Jc::CENTER]);
                    if ($showDurasi) {
                        $table->addCell($wDurasi)->addText($item->formatted_duration, [], ['alignment' => Jc::CENTER]);
                    }
                    $table->addCell($wMateri)->addText($item->materi, ['bold' => true]);
                    $table->addCell($wJp)->addText($item->jp ? (string)$item->jp : '-', [], ['alignment' => Jc::CENTER]);
                    $ketStr = $item->metode ? ($item->keterangan ? "{$item->metode} ({$item->keterangan})" : $item->metode) : ($item->keterangan ?: '-');
                    $table->addCell($wKet)->addText($ketStr, [], ['alignment' => Jc::CENTER]);
                }

                $section->addTextBreak(1);
            }
        }
    }

    protected function resolveImagePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $fullPath = public_path($path);
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }
}
