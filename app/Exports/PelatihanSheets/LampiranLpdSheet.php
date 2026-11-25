<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LampiranLpdSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Lampiran LPD';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Header
                $sheet->setCellValue('A1', 'LAMPIRAN LAPORAN PELAKSANAAN DINAS (LPD) / PELATIHAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A2', 'KEGIATAN: '.strtoupper((string) ($this->data['survey']->name ?? '')));
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A4', 'Nomor Surat Tugas: '.($this->data['nomor_surat_tugas'] ?: '-'));
                $sheet->setCellValue('A5', 'Waktu Pelaksanaan: '.$this->data['periode']);
                $sheet->setCellValue('A6', 'Tempat: '.$this->data['lokasi']);

                // 2. Table Headers
                $startRow = 8;
                $sheet->setCellValue("A{$startRow}", 'No.');
                $sheet->setCellValue("B{$startRow}", 'Nama Peserta');
                $sheet->setCellValue("C{$startRow}", 'NIK / NIP');
                $sheet->setCellValue("D{$startRow}", 'Peran');
                $sheet->setCellValue("E{$startRow}", 'Kelas');
                $sheet->setCellValue("F{$startRow}", 'Asal');
                $sheet->setCellValue("G{$startRow}", 'Tanda Tangan');
                $sheet->mergeCells("G{$startRow}:H{$startRow}");

                $subRow = $startRow + 1;
                $sheet->mergeCells("A{$startRow}:A{$subRow}");
                $sheet->mergeCells("B{$startRow}:B{$subRow}");
                $sheet->mergeCells("C{$startRow}:C{$subRow}");
                $sheet->mergeCells("D{$startRow}:D{$subRow}");
                $sheet->mergeCells("E{$startRow}:E{$subRow}");
                $sheet->mergeCells("F{$startRow}:F{$subRow}");

                $sheet->setCellValue("G{$subRow}", 'Ganjil');
                $sheet->setCellValue("H{$subRow}", 'Genap');

                $this->applyHeaderStyle($sheet, "A{$startRow}:H{$subRow}");
                $sheet->getRowDimension($startRow)->setRowHeight(24);

                // 3. Table Rows
                $rowIdx = $subRow + 1;
                $paymentRows = $this->data['payment_rows'];

                foreach ($paymentRows as $i => $row) {
                    $num = $i + 1;
                    $sheet->setCellValue("A{$rowIdx}", $num);
                    $sheet->setCellValue("B{$rowIdx}", $row['nama']);
                    $sheet->setCellValueExplicit("C{$rowIdx}", (string) $row['nik_or_nip'], DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$rowIdx}", $row['role_pelatihan']);
                    $sheet->setCellValue("E{$rowIdx}", $row['kelas']);
                    $sheet->setCellValue("F{$rowIdx}", $row['asal']);

                    $this->applyZigZagTtd($sheet, $rowIdx, $num, 'G', 'H');

                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getRowDimension($rowIdx)->setRowHeight(24);
                    $rowIdx++;
                }

                if (empty($paymentRows)) {
                    $sheet->setCellValue("A{$rowIdx}", 'Belum ada data peserta.');
                    $sheet->mergeCells("A{$rowIdx}:H{$rowIdx}");
                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $rowIdx++;
                }

                $dataEndRow = $rowIdx - 1;
                $this->applyBorders($sheet, "A{$startRow}:H{$dataEndRow}");

                // 4. Signatures (Penyelenggara & PPK)
                $sigRow = $rowIdx + 2;

                $sheet->setCellValue("B{$sigRow}", 'Mengetahui,');
                $sheet->setCellValue('B'.($sigRow + 1), 'Pejabat Pembuat Komitmen,');
                $sheet->setCellValue('B'.($sigRow + 5), $this->data['ppk']['name']);
                $sheet->getStyle('B'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('B'.($sigRow + 6), 'NIP. '.$this->data['ppk']['nip']);

                $sheet->setCellValue("F{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue('F'.($sigRow + 1), 'Penyelenggara Pelatihan,');
                $sheet->setCellValue('F'.($sigRow + 5), $this->data['penyelenggara']['name']);
                $sheet->getStyle('F'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('F'.($sigRow + 6), 'NIP. '.$this->data['penyelenggara']['nip']);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(26);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(18);
                $sheet->getColumnDimension('G')->setWidth(15);
                $sheet->getColumnDimension('H')->setWidth(15);
            },
        ];
    }
}
