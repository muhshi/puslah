<?php

namespace App\Services;

use App\Models\Survey;
use App\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $logoBase64 = Cache::remember('logo_bps_static_base64', 86400, function () {
            $logoPath = public_path('images/logo_bps.png');
            if (file_exists($logoPath)) {
                return 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
            }

            return null;
        });

        $indaName = $data['first_inda']['nama'] ?? ($data['penyelenggara']['name'] ?? '');
        $indaNip = $data['first_inda']['nip'] ?? ($data['penyelenggara']['nip'] ?? '');

        /** @var DomPdfInstance $pdf */
        $pdf = Pdf::loadView('pdf.daftar_hadir_pelatihan', [
            'survey' => $survey,
            'classes' => $data['attendance_classes'],
            'dates' => $data['dates'],
            'lokasi' => $data['lokasi'],
            'periode' => $data['periode'],
            'inda_name' => $indaName,
            'inda_nip' => $indaNip,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'portrait');

        if (! empty($this->settings->pdf_master_password)) {
            $pdf->setEncryption('', $this->settings->pdf_master_password, ['print']);
        }

        return $pdf;
    }
}
