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
        $innerTable->addCell(4800)->addText($suratUndangan->hal, ['bold' => true]);

        $cRight = $table->addCell(3800, ['valign' => VerticalJc::TOP]);
        $city = $suratUndangan->signer_city ?: 'Demak';
        $cRight->addText($city . ', ' . $suratUndangan->formatted_tanggal, [], ['alignment' => Jc::END]);

        $section->addTextBreak(1);
    }

    protected function addTujuan($section, SuratUndangan $suratUndangan): void
    {
        $lines = explode("\n", (string) $suratUndangan->yth_teks);
        foreach ($lines as $line) {
            $section->addText(trim($line), [], ['spaceAfter' => 20]);
        }
        $section->addTextBreak(1);
    }

    protected function addIsiSurat($section, SuratUndangan $suratUndangan): void
    {
        // Pembuka
        if (!empty($suratUndangan->paragraf_pembuka)) {
            $section->addText($suratUndangan->paragraf_pembuka, [], ['alignment' => Jc::BOTH, 'spaceAfter' => 80]);
        }

        // Isi Konten HTML (Rich Text Editor)
        if (!empty($suratUndangan->isi_surat_html)) {
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $suratUndangan->isi_surat_html, false, false);
        }

        // Penutup
        $section->addText(
            'Demikian disampaikan, untuk dapat dipedomani dan dilaksanakan. Atas perhatiannya diucapkan terima kasih.',
            [],
            ['alignment' => Jc::BOTH, 'spaceBefore' => 120, 'spaceAfter' => 120]
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
        $cTtd->addText($suratUndangan->signer_name ?: 'Kepala BPS Demak', ['bold' => true, 'underline' => 'single'], ['alignment' => Jc::CENTER]);
    }

    protected function addLampiranPeserta($section, SuratUndangan $suratUndangan): void
    {
        // Rujukan pojok kanan atas
        $refTable = $section->addTable(['alignment' => JcTable::END]);
        $refTable->addRow();
        $c = $refTable->addCell(5500);
        $c->addText('Lampiran 1 Surat Kepala BPS Kabupaten Demak', ['bold' => true], ['alignment' => Jc::END, 'spaceAfter' => 20]);
        $c->addText('Nomor : ' . $suratUndangan->nomor_surat, [], ['alignment' => Jc::END, 'spaceAfter' => 20]);
        $c->addText('Tanggal : ' . $suratUndangan->formatted_tanggal, [], ['alignment' => Jc::END, 'spaceAfter' => 40]);

        // Judul Lampiran
        $kegiatan = strtoupper($suratUndangan->survey->name ?? $suratUndangan->hal);
        $tahun = $suratUndangan->tanggal_surat ? $suratUndangan->tanggal_surat->format('Y') : date('Y');
        $section->addText("DAFTAR PESERTA {$kegiatan}", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText("KABUPATEN DEMAK TAHUN {$tahun}", ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);

        $pesertaPerKelas = $this->pdfService->resolvePesertaData($suratUndangan);
        $isMultiClass = count($pesertaPerKelas) > 1 || (count($pesertaPerKelas) === 1 && !isset($pesertaPerKelas['Peserta']));
        $cols = $suratUndangan->kolom_lampiran_peserta ?? ['nip', 'asal_kecamatan', 'sobat_id', 'email'];

        $tableStyle = [
            'borderSize'  => 6,
            'borderColor' => '000000',
            'alignment'   => JcTable::CENTER,
        ];
        $headerStyle = ['bgColor' => 'F2F2F2'];

        foreach ($pesertaPerKelas as $namaKelas => $pesertaList) {
            $table = $section->addTable($tableStyle);

            $colCount = 3 // No, Nama, Jabatan
                + (in_array('nip', $cols) ? 1 : 0)
                + (in_array('asal_kecamatan', $cols) ? 1 : 0)
                + (in_array('sobat_id', $cols) ? 1 : 0)
                + (in_array('email', $cols) ? 1 : 0)
                + (in_array('no_hp', $cols) ? 1 : 0);

            if ($isMultiClass) {
                $table->addRow();
                $table->addCell(10000, ['gridSpan' => $colCount, 'bgColor' => 'E6E6E6'])
                    ->addText('KELAS: ' . strtoupper($namaKelas), ['bold' => true], ['alignment' => Jc::START]);
            }

            // Header Tabel
            $table->addRow();
            $table->addCell(600, $headerStyle)->addText('No', ['bold' => true], ['alignment' => Jc::CENTER]);
            $table->addCell(2800, $headerStyle)->addText('Nama Peserta', ['bold' => true], ['alignment' => Jc::CENTER]);
            if (in_array('nip', $cols)) {
                $table->addCell(1800, $headerStyle)->addText('NIP', ['bold' => true], ['alignment' => Jc::CENTER]);
            }
            $table->addCell(2000, $headerStyle)->addText('Bertugas Sebagai', ['bold' => true], ['alignment' => Jc::CENTER]);
            if (in_array('asal_kecamatan', $cols)) {
                $table->addCell(2000, $headerStyle)->addText('Asal Kecamatan / Alamat', ['bold' => true], ['alignment' => Jc::CENTER]);
            }
            if (in_array('sobat_id', $cols)) {
                $table->addCell(1400, $headerStyle)->addText('Sobat ID', ['bold' => true], ['alignment' => Jc::CENTER]);
            }
            if (in_array('email', $cols)) {
                $table->addCell(2200, $headerStyle)->addText('Email', ['bold' => true], ['alignment' => Jc::CENTER]);
            }
            if (in_array('no_hp', $cols)) {
                $table->addCell(1600, $headerStyle)->addText('No HP / Kontak', ['bold' => true], ['alignment' => Jc::CENTER]);
            }

            // Body Tabel
            foreach ($pesertaList as $idx => $p) {
                $table->addRow();
                $table->addCell(600)->addText((string)($idx + 1), [], ['alignment' => Jc::CENTER]);
                $table->addCell(2800)->addText($p['nama'], ['bold' => true]);
                if (in_array('nip', $cols)) {
                    $table->addCell(1800)->addText($p['nip'] ?: '-', [], ['alignment' => Jc::CENTER]);
                }
                $table->addCell(2000)->addText($p['jabatan'], [], ['alignment' => Jc::CENTER]);
                if (in_array('asal_kecamatan', $cols)) {
                    $table->addCell(2000)->addText($p['asal_kecamatan'] ?: '-');
                }
                if (in_array('sobat_id', $cols)) {
                    $table->addCell(1400)->addText($p['sobat_id'] ?: '-', [], ['alignment' => Jc::CENTER]);
                }
                if (in_array('email', $cols)) {
                    $table->addCell(2200)->addText($p['email'] ?: '-');
                }
                if (in_array('no_hp', $cols)) {
                    $table->addCell(1600)->addText($p['no_hp'] ?: '-', [], ['alignment' => Jc::CENTER]);
                }
            }

            $section->addTextBreak(1);
        }
    }

    protected function addLampiranJadwal($section, SuratUndangan $suratUndangan): void
    {
        // Rujukan pojok kanan atas
        $refTable = $section->addTable(['alignment' => JcTable::END]);
        $refTable->addRow();
        $c = $refTable->addCell(5500);
        $c->addText('Lampiran 2 Surat Kepala BPS Kabupaten Demak', ['bold' => true], ['alignment' => Jc::END, 'spaceAfter' => 20]);
        $c->addText('Nomor : ' . $suratUndangan->nomor_surat, [], ['alignment' => Jc::END, 'spaceAfter' => 20]);
        $c->addText('Tanggal : ' . $suratUndangan->formatted_tanggal, [], ['alignment' => Jc::END, 'spaceAfter' => 40]);

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
            ];
            $headerStyle = ['bgColor' => 'F2F2F2'];

            foreach ($schedulesByDay as $dayLabel => $items) {
                $table = $section->addTable($tableStyle);

                // Group Day
                $gridSpan = $suratUndangan->tampilkan_durasi_jadwal ? 6 : 5;
                $table->addRow();
                $table->addCell(10000, ['gridSpan' => $gridSpan, 'bgColor' => 'E6E6E6'])
                    ->addText('HARI / TANGGAL: ' . strtoupper($dayLabel), ['bold' => true], ['alignment' => Jc::START]);

                // Header
                $table->addRow();
                $table->addCell(600, $headerStyle)->addText('No', ['bold' => true], ['alignment' => Jc::CENTER]);
                $table->addCell(1800, $headerStyle)->addText('Waktu (WIB)', ['bold' => true], ['alignment' => Jc::CENTER]);
                if ($suratUndangan->tampilkan_durasi_jadwal) {
                    $table->addCell(1100, $headerStyle)->addText('Durasi', ['bold' => true], ['alignment' => Jc::CENTER]);
                }
                $table->addCell(3800, $headerStyle)->addText('Materi / Aktivitas', ['bold' => true], ['alignment' => Jc::CENTER]);
                $table->addCell(700, $headerStyle)->addText('JP', ['bold' => true], ['alignment' => Jc::CENTER]);
                $table->addCell(2000, $headerStyle)->addText('Metode / Ket.', ['bold' => true], ['alignment' => Jc::CENTER]);

                // Rows
                foreach ($items as $idx => $item) {
                    $table->addRow();
                    $table->addCell(600)->addText((string)($idx + 1), [], ['alignment' => Jc::CENTER]);
                    $waktuStr = ($item->waktu_mulai ?: '') . ' - ' . ($item->waktu_selesai ?: '');
                    $table->addCell(1800)->addText($waktuStr, [], ['alignment' => Jc::CENTER]);
                    if ($suratUndangan->tampilkan_durasi_jadwal) {
                        $table->addCell(1100)->addText($item->formatted_duration, [], ['alignment' => Jc::CENTER]);
                    }
                    $table->addCell(3800)->addText($item->materi, ['bold' => true]);
                    $table->addCell(700)->addText($item->jp ? (string)$item->jp : '-', [], ['alignment' => Jc::CENTER]);
                    $ketStr = $item->metode ? ($item->keterangan ? "{$item->metode} ({$item->keterangan})" : $item->metode) : ($item->keterangan ?: '-');
                    $table->addCell(2000)->addText($ketStr, [], ['alignment' => Jc::CENTER]);
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
