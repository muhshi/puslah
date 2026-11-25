<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TandaTerimaAtkSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Tanda Terima ATK';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Header
                $sheet->setCellValue('A1', 'BADAN PUSAT STATISTIK KABUPATEN DEMAK');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                $sheet->setCellValue('A2', 'TANDA TERIMA PERLENGKAPAN PESERTA (ATK) PELATIHAN');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A3', strtoupper((string) ($this->data['survey']->name ?? '')));
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

                // ATK Items list
                $atkItems = $this->data['atk_items'] ?? ['Totebag', 'Pulpen', 'Pensil 2B', 'Penghapus', 'Rautan', 'Blocknote'];
                $atkDesc = is_array($atkItems) ? implode(', ', $atkItems) : (string) $atkItems;

                $sheet->setCellValue('A5', 'Kegiatan: '.($this->data['survey']->name ?? '-'));
                $sheet->setCellValue('A6', 'Waktu Pelaksanaan: '.$this->data['periode']);
                $sheet->setCellValue('A7', 'Paket Perlengkapan: '.$atkDesc);

                // 2. Table Headers
                $startRow = 9;
                $sheet->setCellValue("A{$startRow}", 'No.');
                $sheet->setCellValue("B{$startRow}", 'Nama Peserta');
                $sheet->setCellValue("C{$startRow}", 'Kelas');
                $sheet->setCellValue("D{$startRow}", 'Asal');
                $sheet->setCellValue("E{$startRow}", 'Rincian Barang yang Diterima');
                $sheet->setCellValue("F{$startRow}", 'Tanda Tangan');
                $sheet->mergeCells("F{$startRow}:G{$startRow}");

                $subRow = $startRow + 1;
                $sheet->mergeCells("A{$startRow}:A{$subRow}");
                $sheet->mergeCells("B{$startRow}:B{$subRow}");
                $sheet->mergeCells("C{$startRow}:C{$subRow}");
                $sheet->mergeCells("D{$startRow}:D{$subRow}");
                $sheet->mergeCells("E{$startRow}:E{$subRow}");

                $sheet->setCellValue("F{$subRow}", 'Ganjil');
                $sheet->setCellValue("G{$subRow}", 'Genap');

                $this->applyHeaderStyle($sheet, "A{$startRow}:G{$subRow}");
                $sheet->getRowDimension($startRow)->setRowHeight(24);

                // 3. Table Rows
                $rowIdx = $subRow + 1;
                $paymentRows = $this->data['payment_rows'];

                foreach ($paymentRows as $i => $row) {
                    $num = $i + 1;
                    $sheet->setCellValue("A{$rowIdx}", $num);
                    $sheet->setCellValue("B{$rowIdx}", $row['nama']);
                    $sheet->setCellValue("C{$rowIdx}", $row['kelas']);
                    $sheet->setCellValue("D{$rowIdx}", $row['asal']);
                    $sheet->setCellValue("E{$rowIdx}", '1 Paket ATK Lengkap');

                    $this->applyZigZagTtd($sheet, $rowIdx, $num, 'F', 'G');

                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getRowDimension($rowIdx)->setRowHeight(24);
                    $rowIdx++;
                }

                if (empty($paymentRows)) {
                    $sheet->setCellValue("A{$rowIdx}", 'Belum ada data peserta.');
                    $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $rowIdx++;
                }

                $dataEndRow = $rowIdx - 1;
                $this->applyBorders($sheet, "A{$startRow}:G{$dataEndRow}");

                // 4. Signatures (Penyelenggara)
                $sigRow = $rowIdx + 2;
                $sheet->setCellValue("E{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue('E'.($sigRow + 1), 'Penyelenggara Pelatihan,');
                $sheet->setCellValue('E'.($sigRow + 5), $this->data['penyelenggara']['name']);
                $sheet->getStyle('E'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('E'.($sigRow + 6), 'NIP. '.$this->data['penyelenggara']['nip']);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(18);
                $sheet->getColumnDimension('E')->setWidth(26);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(15);
            },
        ];
    }
}
