<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LampiranRiilSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Lampiran Riil';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Header
                $sheet->setCellValue('A1', 'DAFTAR LAMPIRAN DAFTAR PENGELUARAN RIIL TRANSPORTASI LOKAL');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                $sheet->setCellValue('A2', 'KEGIATAN: '.strtoupper((string) ($this->data['survey']->name ?? '')));
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A4', 'Nomor Surat Tugas: '.($this->data['nomor_surat_tugas'] ?: '-'));
                $sheet->setCellValue('A5', 'Tempat Kegiatan: '.$this->data['lokasi']);

                // 2. Table Headers
                $startRow = 7;
                $sheet->setCellValue("A{$startRow}", 'No.');
                $sheet->setCellValue("B{$startRow}", 'Nama Peserta');
                $sheet->setCellValue("C{$startRow}", 'NIK / NIP');
                $sheet->setCellValue("D{$startRow}", 'Asal');
                $sheet->setCellValue("E{$startRow}", 'Tujuan');
                $sheet->setCellValue("F{$startRow}", "Biaya Transport\n(Rp)");
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
                $dataStartRow = $rowIdx;
                $paymentRows = $this->data['payment_rows'];

                foreach ($paymentRows as $i => $row) {
                    $num = $i + 1;
                    $sheet->setCellValue("A{$rowIdx}", $num);
                    $sheet->setCellValue("B{$rowIdx}", $row['nama']);
                    $sheet->setCellValueExplicit("C{$rowIdx}", (string) $row['nik_or_nip'], DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$rowIdx}", $row['asal']);
                    $sheet->setCellValue("E{$rowIdx}", $row['tujuan']);
                    $sheet->setCellValue("F{$rowIdx}", $row['transport']);

                    $this->applyZigZagTtd($sheet, $rowIdx, $num, 'G', 'H');

                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getRowDimension($rowIdx)->setRowHeight(24);
                    $rowIdx++;
                }

                $dataEndRow = max($dataStartRow, $rowIdx - 1);

                if (empty($paymentRows)) {
                    $sheet->setCellValue("A{$rowIdx}", 'Belum ada data pengeluaran riil.');
                    $sheet->mergeCells("A{$rowIdx}:H{$rowIdx}");
                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $dataEndRow = $rowIdx;
                    $rowIdx++;
                }

                // 4. Total Row with active SUM formula
                $totalRow = $rowIdx;
                $sheet->setCellValue("A{$totalRow}", 'JUMLAH');
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->getStyle("A{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if (! empty($paymentRows)) {
                    $sheet->setCellValue("F{$totalRow}", "=SUM(F{$dataStartRow}:F{$dataEndRow})");
                } else {
                    $sheet->setCellValue("F{$totalRow}", 0);
                }
                $sheet->getStyle("F{$totalRow}")->getFont()->setBold(true);

                $sheet->getStyle("F{$dataStartRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                $this->applyBorders($sheet, "A{$startRow}:H{$totalRow}");

                // 5. Signatures (PPK)
                $sigRow = $totalRow + 3;
                $sheet->setCellValue("F{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue('F'.($sigRow + 1), 'Pejabat Pembuat Komitmen,');
                $sheet->setCellValue('F'.($sigRow + 5), $this->data['ppk']['name']);
                $sheet->getStyle('F'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('F'.($sigRow + 6), 'NIP. '.$this->data['ppk']['nip']);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(22);
                $sheet->getColumnDimension('D')->setWidth(18);
                $sheet->getColumnDimension('E')->setWidth(18);
                $sheet->getColumnDimension('F')->setWidth(20);
                $sheet->getColumnDimension('G')->setWidth(15);
                $sheet->getColumnDimension('H')->setWidth(15);
            },
        ];
    }
}
