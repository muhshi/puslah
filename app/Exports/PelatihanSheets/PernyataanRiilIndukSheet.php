<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PernyataanRiilIndukSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Pernyataan Riil';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Header
                $sheet->setCellValue('A1', 'BADAN PUSAT STATISTIK KABUPATEN DEMAK');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                $sheet->setCellValue('A3', 'SURAT PERNYATAAN PENYELENGGARAAN TRANSPORT LOKAL');
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A4', 'Nomor: '.($this->data['nomor_surat_tugas'] ?: '-'));
                $sheet->getStyle('A4')->getFont()->setBold(true);

                // Body description
                $sheet->setCellValue('A6', 'Yang bertanda tangan di bawah ini menyatakan dengan sesungguhnya bahwa:');
                $sheet->setCellValue('A7', '1. Biaya transport lokal dan uang harian pelaksanaan pelatihan petugas '.($this->data['survey']->name ?? '').' telah dilaksanakan sesuai ketentuan.');
                $sheet->setCellValue('A8', '2. Rincian pengeluaran riil transportasi lokal terlampir dan merupakan bagian yang tidak terpisahkan dari surat pernyataan ini.');
                $sheet->setCellValue('A9', '3. Apabila di kemudian hari terdapat kelebihan atas pembayaran tersebut, kami bersedia untuk menyetorkan kelebihan tersebut ke Kas Negara.');

                // Summary Table
                $startRow = 11;
                $sheet->setCellValue("A{$startRow}", 'No.');
                $sheet->setCellValue("B{$startRow}", 'Uraian Pengeluaran');
                $sheet->setCellValue("C{$startRow}", 'Jumlah Peserta');
                $sheet->setCellValue("D{$startRow}", 'Jumlah Biaya (Rp)');

                $this->applyHeaderStyle($sheet, "A{$startRow}:D{$startRow}");

                $pesertaCount = count($this->data['payment_rows']);
                $totalTransport = array_sum(array_column($this->data['payment_rows'], 'transport'));

                $rowIdx = $startRow + 1;
                $sheet->setCellValue("A{$rowIdx}", '1');
                $sheet->setCellValue("B{$rowIdx}", 'Biaya Transportasi Lokal Pelatihan Peserta');
                $sheet->setCellValue("C{$rowIdx}", $pesertaCount.' Orang');
                $sheet->setCellValue("D{$rowIdx}", $totalTransport);

                $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');

                $this->applyBorders($sheet, "A{$startRow}:D{$rowIdx}");

                // Signatures: PPK, Penyelenggara, Mengetahui Kepala BPS
                $sigRow = $rowIdx + 3;

                // Left: PPK
                $sheet->setCellValue("A{$sigRow}", 'Pejabat Pembuat Komitmen,');
                $sheet->setCellValue('A'.($sigRow + 4), $this->data['ppk']['name']);
                $sheet->getStyle('A'.($sigRow + 4))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('A'.($sigRow + 5), 'NIP. '.$this->data['ppk']['nip']);

                // Center: Penyelenggara / Pelaksana
                $sheet->setCellValue("C{$sigRow}", 'Penyelenggara Kegiatan,');
                $sheet->setCellValue('C'.($sigRow + 4), $this->data['penyelenggara']['name']);
                $sheet->getStyle('C'.($sigRow + 4))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('C'.($sigRow + 5), 'NIP. '.$this->data['penyelenggara']['nip']);

                // Below Center: Mengetahui Kepala BPS
                $kepalaRow = $sigRow + 7;
                $sheet->setCellValue("B{$kepalaRow}", 'Mengetahui:');
                $sheet->setCellValue('B'.($kepalaRow + 1), $this->data['kepala']['title'].',');
                $sheet->setCellValue('B'.($kepalaRow + 5), $this->data['kepala']['name']);
                $sheet->getStyle('B'.($kepalaRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('B'.($kepalaRow + 6), 'NIP. '.$this->data['kepala']['nip']);

                // Widths
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(40);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(24);
            },
        ];
    }
}
