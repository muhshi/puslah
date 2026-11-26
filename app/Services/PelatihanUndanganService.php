<?php

namespace App\Services;

use App\Models\Survey;
use App\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PelatihanUndanganService
{
    public function __construct(
        protected PelatihanSpjDataBuilder $dataBuilder,
        protected SystemSettings $settings,
    ) {}

    /**
     * Download generated Word document for Pelatihan invitation and class participant lists.
     */
    public function downloadUndanganWord(Survey $survey): BinaryFileResponse
    {
        $data = $this->dataBuilder->build($survey);

        // Resolve template path (custom from settings or default resource)
        $customPath = $this->settings->undangan_pelatihan_template_path;
        if ($customPath && file_exists(storage_path('app/public/'.$customPath))) {
            $templatePath = storage_path('app/public/'.$customPath);
        } else {
            $templatePath = resource_path('templates/template_undangan_pelatihan.docx');
        }

        $template = new TemplateProcessor($templatePath);

        // Format dates
        $tanggalSurat = $survey->tanggal_surat_undangan
            ? Carbon::parse($survey->tanggal_surat_undangan)->translatedFormat('d F Y')
            : ($survey->start_date ? Carbon::parse($survey->start_date)->translatedFormat('d F Y') : date('d F Y'));

        $nomorSurat = $survey->nomor_surat_undangan ?: 'B-..../33210/'.date('Y');
        $rujukanSurat = $survey->rujukan_surat ?: ($survey->dasar_surat ?: 'DIPA BPS Kabupaten Demak Tahun Anggaran '.date('Y'));
        $hal = str_starts_with(strtolower(trim((string) $survey->name)), 'pelatihan')
            ? 'Undangan '.$survey->name
            : 'Undangan Pelatihan '.$survey->name;

        // Set Header & Letter Variables
        $template->setValue('nomor_surat', $nomorSurat);
        $template->setValue('hal', $hal);
        $template->setValue('tanggal_surat', $tanggalSurat);
        $template->setValue('nama_kegiatan', $survey->name);
        $template->setValue('rujukan_surat', $rujukanSurat);
        $template->setValue('periode_pelatihan', $data['periode']);
        $template->setValue('jumlah_hari', (string) $data['jumlah_hari']);
        $template->setValue('lokasi', $data['lokasi']);
        $template->setValue('link_materi', $survey->link_materi ?? '');

        $template->setValue('jabatan_kepala', $data['kepala']['title']);
        $template->setValue('nama_kepala', $data['kepala']['name']);
        $template->setValue('nip_kepala', $data['kepala']['nip']);

        // Process Lampiran 1: Classes & Participants (gracefully supports custom templates)
        $allClasses = $data['all_classes'];
        $classCount = count($allClasses);
        $variables = $template->getVariables();

        if (in_array('kelas_block', $variables, true)) {
            if ($classCount === 0) {
                $template->cloneBlock('kelas_block', 0);
            } else {
                $template->cloneBlock('kelas_block', $classCount, true, true);

                foreach ($allClasses as $cIdx => $classData) {
                    $c = $cIdx + 1;
                    $template->setValue("kelas_nama#{$c}", $classData['kelas_nama']);

                    $rows = $classData['rows'];
                    $rowCount = count($rows);
                    $clonedVars = $template->getVariables();

                    if (in_array("no#{$c}", $clonedVars, true)) {
                        if ($rowCount > 0) {
                            $template->cloneRow("no#{$c}", $rowCount);

                            foreach ($rows as $rIdx => $row) {
                                $r = $rIdx + 1;
                                $template->setValue("no#{$c}#{$r}", (string) $r);
                                $template->setValue("nama#{$c}#{$r}", $row['nama']);
                                $template->setValue("asal#{$c}#{$r}", $row['asal']);
                                $template->setValue("bertugas_sebagai#{$c}#{$r}", $row['bertugas_sebagai']);
                            }
                        } else {
                            $template->setValue("no#{$c}", '-');
                            $template->setValue("nama#{$c}", '-');
                            $template->setValue("asal#{$c}", '-');
                            $template->setValue("bertugas_sebagai#{$c}", '-');
                        }
                    }
                }
            }
        } elseif (in_array('no', $variables, true)) {
            $flatRows = collect($allClasses)->flatMap(fn (array $c) => $c['rows'])->values();
            if ($flatRows->isNotEmpty()) {
                $template->cloneRow('no', $flatRows->count());
                foreach ($flatRows as $rIdx => $row) {
                    $r = $rIdx + 1;
                    $template->setValue("no#{$r}", (string) $r);
                    $template->setValue("nama#{$r}", $row['nama']);
                    $template->setValue("asal#{$r}", $row['asal']);
                    $template->setValue("bertugas_sebagai#{$r}", $row['bertugas_sebagai']);
                }
            } else {
                $template->setValue('no', '-');
                $template->setValue('nama', '-');
                $template->setValue('asal', '-');
                $template->setValue('bertugas_sebagai', '-');
            }
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'undangan_').'.docx';
        $template->saveAs($tempFile);

        $downloadFilename = 'Undangan_Pelatihan_'.Str::slug($survey->name ?: 'Kegiatan').'.docx';

        return response()->download(
            $tempFile,
            $downloadFilename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    /**
     * Render DomPDF instance for Quick-Print Pelatihan Attendance Sheet.
     */
    public function renderDaftarHadirPdf(Survey $survey): DomPdfInstance
    {
        $data = $this->dataBuilder->build($survey);

        $indaName = $data['first_inda']['nama'] ?? ($data['penyelenggara']['name'] ?? '');
        $indaNip = $data['first_inda']['nip'] ?? ($data['penyelenggara']['nip'] ?? '');

        return $this->loadProtectedPdf('pdf.daftar_hadir_pelatihan', [
            'survey' => $survey,
            'classes' => $data['attendance_classes'],
            'dates' => $data['dates'],
            'lokasi' => $data['lokasi'],
            'periode' => $data['periode'],
            'inda_name' => $indaName,
            'inda_nip' => $indaNip,
            'logoBase64' => $this->getLogoBase64(),
        ]);
    }

    /**
     * Render DomPDF instance for Pernyataan Pengeluaran Riil (per individu).
     */
    public function renderPernyataanRiilPdf(Survey $survey): DomPdfInstance
    {
        return $this->loadProtectedPdf('pdf.pernyataan_riil_pelatihan', [
            'survey' => $survey,
            'data' => $this->dataBuilder->build($survey),
        ]);
    }

    /**
     * Render DomPDF instance for Surat Pernyataan Tidak Menggunakan Kendaraan Dinas (per individu).
     */
    public function renderPernyataanTidakKendaraanPdf(Survey $survey): DomPdfInstance
    {
        return $this->loadProtectedPdf('pdf.pernyataan_tidak_kendaraan_pelatihan', [
            'survey' => $survey,
            'data' => $this->dataBuilder->build($survey),
        ]);
    }

    /**
     * Return sorted unique class names present in the Pelatihan.
     *
     * @return array<int, string>
     */
    public function getAvailableClasses(Survey $survey): array
    {
        $classes = $survey->surveyUsers()
            ->pluck('kelas')
            ->map(fn (?string $kelas) => filled($kelas) ? trim($kelas) : 'Kelas A')
            ->unique()
            ->values()
            ->all();

        if ($classes === []) {
            return ['Kelas A'];
        }

        usort($classes, 'strnatcasecmp');

        return $classes;
    }

    /**
     * Render 1 complete SPJ PDF bundle for a specific class (or all participants when $kelas is null).
     */
    public function renderBundelSpjKelasPdf(Survey $survey, ?string $kelas = null): DomPdfInstance
    {
        $data = $this->dataBuilder->build($survey);

        $kelasLabel = filled($kelas) ? trim($kelas) : ($this->getAvailableClasses($survey)[0] ?? 'Kelas A');

        $classPaymentRows = array_values(array_filter(
            $data['payment_rows'] ?? [],
            fn (array $row): bool => $kelas === null || strcasecmp((string) ($row['kelas'] ?? 'Kelas A'), $kelasLabel) === 0
        ));

        $attendanceGroup = collect($data['attendance_classes'] ?? [])
            ->first(fn (array $group): bool => strcasecmp((string) ($group['kelas_nama'] ?? ''), $kelasLabel) === 0);

        $classAttendanceRows = $attendanceGroup['rows'] ?? $classPaymentRows;

        $classGrandTotal = (int) array_sum(array_column($classPaymentRows, 'total'));
        $classTerbilang = SppdService::terbilang($classGrandTotal).' rupiah';

        return $this->loadProtectedPdf('pdf.bundel_spj_pelatihan', [
            'survey' => $survey,
            'data' => $data,
            'kelasNama' => $kelasLabel,
            'classAttendanceRows' => $classAttendanceRows,
            'classPaymentRows' => $classPaymentRows,
            'classGrandTotal' => $classGrandTotal,
            'classTerbilang' => $classTerbilang,
            'logoBase64' => $this->getLogoBase64(),
        ]);
    }

    /**
     * Download 1 bundled PDF directly (single class) or a ZIP archive containing 1 complete PDF per class.
     */
    public function downloadBundelSpjKelas(Survey $survey, ?string $selectedKelas = null): BinaryFileResponse
    {
        $classes = $this->getAvailableClasses($survey);
        $slug = Str::slug($survey->name ?: 'Pelatihan');

        if (filled($selectedKelas) && $selectedKelas !== '__ALL_ZIP__') {
            $targetClasses = [$selectedKelas];
        } else {
            $targetClasses = $classes;
        }

        if (count($targetClasses) <= 1) {
            $kelas = $targetClasses[0] ?? 'Kelas A';
            $pdfOutput = $this->renderBundelSpjKelasPdf($survey, $kelas)->output();
            $tempPdf = tempnam(sys_get_temp_dir(), 'bundel_spj_').'.pdf';
            file_put_contents($tempPdf, $pdfOutput);

            $filename = "Bundel_SPJ_{$slug}_".Str::slug($kelas).'.pdf';

            return response()->download($tempPdf, $filename, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'bundel_spj_zip_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($targetClasses as $kelas) {
            $pdfOutput = $this->renderBundelSpjKelasPdf($survey, $kelas)->output();
            $entryName = "Bundel_SPJ_{$slug}_".Str::slug($kelas).'.pdf';
            $zip->addFromString($entryName, $pdfOutput);
        }

        $zip->close();

        return response()->download($zipPath, "Bundel_SPJ_Per_Kelas_{$slug}.zip", [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private ?string $cachedLogoBase64 = null;

    private function getLogoBase64(): ?string
    {
        if ($this->cachedLogoBase64 !== null) {
            return $this->cachedLogoBase64;
        }

        $logoPath = public_path('images/logo_bps.png');
        if (file_exists($logoPath)) {
            return $this->cachedLogoBase64 = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        return null;
    }

    /**
     * Load a DomPDF A4 portrait document and apply optional print-only PDF protection.
     *
     * @param  array<string, mixed>  $viewData
     */
    private function loadProtectedPdf(string $view, array $viewData): DomPdfInstance
    {
        /** @var DomPdfInstance $pdf */
        $pdf = Pdf::loadView($view, $viewData)->setPaper('a4', 'portrait');

        if (! empty($this->settings->pdf_master_password)) {
            $pdf->setEncryption('', $this->settings->pdf_master_password, ['print']);
        }

        return $pdf;
    }
}
