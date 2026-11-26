<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LampiranTidakKendaraanSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Lampiran Tdk Kendaraan';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Header
                $sheet->setCellValue('A1', 'LAMPIRAN SURAT PERNYATAAN TIDAK MENGGUNAKAN KENDARAAN DINAS');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A2', 'KEGIATAN: '.strtoupper((string) ($this->data['survey']->name ?? '')));
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A4', 'Nomor Surat Tugas: '.($this->data['nomor_surat_tugas'] ?: '-'));
                $sheet->setCellValue('A5', 'Waktu Pelaksanaan: '.$this->data['periode']);

                // 2. Table Headers
                $startRow = 7;
                $sheet->setCellValue("A{$startRow}", 'No.');
                $sheet->setCellValue("B{$startRow}", 'Nama Peserta');
                $sheet->setCellValue("C{$startRow}", 'NIK / NIP');
                $sheet->setCellValue("D{$startRow}", 'Asal / Instansi');
                $sheet->setCellValue("E{$startRow}", 'Tanda Tangan');
                $sheet->mergeCells("E{$startRow}:F{$startRow}");

                $subRow = $startRow + 1;
                $sheet->mergeCells("A{$startRow}:A{$subRow}");
                $sheet->mergeCells("B{$startRow}:B{$subRow}");
                $sheet->mergeCells("C{$startRow}:C{$subRow}");
                $sheet->mergeCells("D{$startRow}:D{$subRow}");

                $sheet->setCellValue("E{$subRow}", 'Ganjil');
                $sheet->setCellValue("F{$subRow}", 'Genap');

                $this->applyHeaderStyle($sheet, "A{$startRow}:F{$subRow}");
                $sheet->getRowDimension($startRow)->setRowHeight(24);

                // 3. Table Rows
                $rowIdx = $subRow + 1;
                $paymentRows = $this->data['payment_rows'];

                foreach ($paymentRows as $i => $row) {
                    $num = $i + 1;
                    $sheet->setCellValue("A{$rowIdx}", $num);
                    $sheet->setCellValue("B{$rowIdx}", $row['nama']);
                    $sheet->setCellValueExplicit("C{$rowIdx}", (string) $row['nik_or_nip'], DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$rowIdx}", $row['asal']);

                    $this->applyZigZagTtd($sheet, $rowIdx, $num, 'E', 'F');

                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getRowDimension($rowIdx)->setRowHeight(24);
                    $rowIdx++;
                }

                if (empty($paymentRows)) {
                    $sheet->setCellValue("A{$rowIdx}", 'Belum ada data peserta.');
                    $sheet->mergeCells("A{$rowIdx}:F{$rowIdx}");
                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $rowIdx++;
                }

                $dataEndRow = $rowIdx - 1;
                $this->applyBorders($sheet, "A{$startRow}:F{$dataEndRow}");

                // 4. Signatures (PPK)
                $sigRow = $rowIdx + 2;
                $sheet->setCellValue("D{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue('D'.($sigRow + 1), 'Pejabat Pembuat Komitmen,');
                $sheet->setCellValue('D'.($sigRow + 5), $this->data['ppk']['name']);
                $sheet->getStyle('D'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('D'.($sigRow + 6), 'NIP. '.$this->data['ppk']['nip']);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(22);
                $sheet->getColumnDimension('D')->setWidth(24);
                $sheet->getColumnDimension('E')->setWidth(16);
                $sheet->getColumnDimension('F')->setWidth(16);
            },
        ];
    }
}
