<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BuktiPenerimaanUangSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Bukti Penerimaan Uang';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Header Information
                $sheet->setCellValue('A1', 'DAFTAR BUKTI PENERIMAAN UANG TRANSPORT DAN UANG HARIAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                $sheet->setCellValue('A2', 'PELATIHAN PETUGAS '.strtoupper((string) ($this->data['survey']->name ?? '')));
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A3', 'TAHUN ANGGARAN '.date('Y'));
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

                // DIPA Metadata Block
                $dipa = $this->data['dipa'];
                $sheet->setCellValue('A5', 'Program');
                $sheet->setCellValue('B5', ': '.$dipa['program']);
                $sheet->setCellValue('A6', 'Kegiatan');
                $sheet->setCellValue('B6', ': '.$dipa['kegiatan']);
                $sheet->setCellValue('A7', 'KRO / RO');
                $sheet->setCellValue('B7', ': '.$dipa['klasifikasi_ro'].' / '.$dipa['rincian_output']);
                $sheet->setCellValue('A8', 'Komponen / MAK');
                $sheet->setCellValue('B8', ': '.$dipa['komponen'].' / '.$dipa['mak']);

                // 2. Table Headers
                $startRow = 10;
                $sheet->setCellValue("A{$startRow}", 'No.');
                $sheet->setCellValue("B{$startRow}", 'Nama Penerima');
                $sheet->setCellValue("C{$startRow}", 'NIK / NIP');
                $sheet->setCellValue("D{$startRow}", 'Bank');
                $sheet->setCellValue("E{$startRow}", 'No. Rekening');
                $sheet->setCellValue("F{$startRow}", 'Asal');
                $sheet->setCellValue("G{$startRow}", 'Tujuan');
                $sheet->setCellValue("H{$startRow}", "Biaya\nTransport (Rp)");
                $sheet->setCellValue("I{$startRow}", "Uang Harian\n/ Hari (Rp)");
                $sheet->setCellValue("J{$startRow}", "Jml\nHari");
                $sheet->setCellValue("K{$startRow}", "Total Diterima\n(Rp)");
                $sheet->setCellValue("L{$startRow}", 'Tanda Tangan');
                $sheet->mergeCells("L{$startRow}:M{$startRow}");

                $subRow = $startRow + 1;
                $sheet->mergeCells("A{$startRow}:A{$subRow}");
                $sheet->mergeCells("B{$startRow}:B{$subRow}");
                $sheet->mergeCells("C{$startRow}:C{$subRow}");
                $sheet->mergeCells("D{$startRow}:D{$subRow}");
                $sheet->mergeCells("E{$startRow}:E{$subRow}");
                $sheet->mergeCells("F{$startRow}:F{$subRow}");
                $sheet->mergeCells("G{$startRow}:G{$subRow}");
                $sheet->mergeCells("H{$startRow}:H{$subRow}");
                $sheet->mergeCells("I{$startRow}:I{$subRow}");
                $sheet->mergeCells("J{$startRow}:J{$subRow}");
                $sheet->mergeCells("K{$startRow}:K{$subRow}");

                $sheet->setCellValue("L{$subRow}", 'Ganjil');
                $sheet->setCellValue("M{$subRow}", 'Genap');

                $this->applyHeaderStyle($sheet, "A{$startRow}:M{$subRow}");
                $sheet->getRowDimension($startRow)->setRowHeight(28);
                $sheet->getRowDimension($subRow)->setRowHeight(18);

                // 3. Table Rows
                $rowIdx = $subRow + 1;
                $dataStartRow = $rowIdx;
                $paymentRows = $this->data['payment_rows'];

                foreach ($paymentRows as $i => $row) {
                    $num = $i + 1;
                    $sheet->setCellValue("A{$rowIdx}", $num);
                    $sheet->setCellValue("B{$rowIdx}", $row['nama']);
                    $sheet->setCellValueExplicit("C{$rowIdx}", (string) $row['nik_or_nip'], DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$rowIdx}", $row['bank']);
                    $sheet->setCellValueExplicit("E{$rowIdx}", (string) $row['rekening'], DataType::TYPE_STRING);
                    $sheet->setCellValue("F{$rowIdx}", $row['asal']);
                    $sheet->setCellValue("G{$rowIdx}", $row['tujuan']);
                    $sheet->setCellValue("H{$rowIdx}", $row['transport']);
                    $sheet->setCellValue("I{$rowIdx}", $row['uang_harian']);
                    $sheet->setCellValue("J{$rowIdx}", $row['hari']);

                    // Active multiplication and addition formula
                    $sheet->setCellValue("K{$rowIdx}", "=(H{$rowIdx}+(I{$rowIdx}*J{$rowIdx}))");

                    $this->applyZigZagTtd($sheet, $rowIdx, $num, 'L', 'M');

                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getRowDimension($rowIdx)->setRowHeight(24);
                    $rowIdx++;
                }

                $dataEndRow = max($dataStartRow, $rowIdx - 1);

                if (empty($paymentRows)) {
                    $sheet->setCellValue("A{$rowIdx}", 'Belum ada data pembayaran.');
                    $sheet->mergeCells("A{$rowIdx}:M{$rowIdx}");
                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $dataEndRow = $rowIdx;
                    $rowIdx++;
                }

                // 4. Total Row with active SUM formula
                $totalRow = $rowIdx;
                $sheet->setCellValue("A{$totalRow}", 'JUMLAH');
                $sheet->mergeCells("A{$totalRow}:J{$totalRow}");
                $sheet->getStyle("A{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if (! empty($paymentRows)) {
                    $sheet->setCellValue("K{$totalRow}", "=SUM(K{$dataStartRow}:K{$dataEndRow})");
                } else {
                    $sheet->setCellValue("K{$totalRow}", 0);
                }
                $sheet->getStyle("K{$totalRow}")->getFont()->setBold(true);

                // Format numbers to rupiah format
                $sheet->getStyle("H{$dataStartRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("K{$dataStartRow}:K{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Borders
                $this->applyBorders($sheet, "A{$startRow}:M{$totalRow}");

                // Terbilang Row
                $terbilangRow = $totalRow + 1;
                $sheet->setCellValue("A{$terbilangRow}", 'Terbilang: '.ucfirst($this->data['terbilang']));
                $sheet->mergeCells("A{$terbilangRow}:M{$terbilangRow}");
                $sheet->getStyle("A{$terbilangRow}")->getFont()->setItalic(true);

                // 5. Signatures (3 parties: Bendahara, PPK, Pembuat Daftar)
                $sigRow = $terbilangRow + 3;

                // Left: PPK
                $sheet->setCellValue("B{$sigRow}", 'Setuju dibayar:');
                $sheet->setCellValue('B'.($sigRow + 1), 'Pejabat Pembuat Komitmen,');
                $sheet->setCellValue('B'.($sigRow + 5), $this->data['ppk']['name']);
                $sheet->getStyle('B'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('B'.($sigRow + 6), 'NIP. '.$this->data['ppk']['nip']);

                // Center: Bendahara
                $sheet->setCellValue("F{$sigRow}", 'Lunas dibayar tgl:');
                $sheet->setCellValue('F'.($sigRow + 1), 'Bendahara Pengeluaran,');
                $sheet->setCellValue('F'.($sigRow + 5), $this->data['bendahara']['name']);
                $sheet->getStyle('F'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('F'.($sigRow + 6), 'NIP. '.$this->data['bendahara']['nip']);

                // Right: Pembuat Daftar
                $sheet->setCellValue("K{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue('K'.($sigRow + 1), 'Pembuat Daftar / Penyelenggara,');
                $sheet->setCellValue('K'.($sigRow + 5), $this->data['penyelenggara']['name']);
                $sheet->getStyle('K'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('K'.($sigRow + 6), 'NIP. '.$this->data['penyelenggara']['nip']);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(26);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->getColumnDimension('E')->setWidth(20);
                $sheet->getColumnDimension('F')->setWidth(16);
                $sheet->getColumnDimension('G')->setWidth(16);
                $sheet->getColumnDimension('H')->setWidth(16);
                $sheet->getColumnDimension('I')->setWidth(16);
                $sheet->getColumnDimension('J')->setWidth(8);
                $sheet->getColumnDimension('K')->setWidth(18);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(15);
            },
        ];
    }
}
