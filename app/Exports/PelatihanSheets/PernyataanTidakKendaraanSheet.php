<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class PernyataanTidakKendaraanSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Pernyataan Tdk Kendaraan';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Header
                $sheet->setCellValue('A1', 'BADAN PUSAT STATISTIK KABUPATEN DEMAK');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                $sheet->setCellValue('A3', 'SURAT PERNYATAAN TIDAK MENGGUNAKAN KENDARAAN DINAS');
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A4', 'Nomor: '.($this->data['nomor_surat_tugas'] ?: '-'));
                $sheet->getStyle('A4')->getFont()->setBold(true);

                // 2. Statement Body
                $sheet->setCellValue('A6', 'Yang bertanda tangan di bawah ini:');
                $sheet->setCellValue('B7', 'Nama');
                $sheet->setCellValue('C7', ': '.$this->data['ppk']['name']);
                $sheet->setCellValue('B8', 'NIP');
                $sheet->setCellValue('C8', ': '.$this->data['ppk']['nip']);
                $sheet->setCellValue('B9', 'Jabatan');
                $sheet->setCellValue('C9', ': '.$this->data['ppk']['title']);
                $sheet->setCellValue('B10', 'Unit Kerja');
                $sheet->setCellValue('C10', ': BPS Kabupaten Demak');

                $sheet->setCellValue('A12', 'Dengan ini menyatakan dengan sesungguhnya bahwa dalam rangka pelaksanaan kegiatan:');
                $sheet->setCellValue('B13', 'Nama Kegiatan');
                $sheet->setCellValue('C13', ': '.($this->data['survey']->name ?? '-'));
                $sheet->setCellValue('B14', 'Waktu Pelaksanaan');
                $sheet->setCellValue('C14', ': '.$this->data['periode']);
                $sheet->setCellValue('B15', 'Tempat / Lokasi');
                $sheet->setCellValue('C15', ': '.$this->data['lokasi']);

                $sheet->setCellValue('A17', 'Seluruh peserta pelatihan (sebagaimana daftar terlampir) TIDAK MENGGUNAKAN sarana transportasi / kendaraan dinas,');
                $sheet->setCellValue('A18', 'melainkan menggunakan kendaraan pribadi / transportasi umum, sehingga berhak menerima penggantian biaya transportasi lokal.');

                $sheet->setCellValue('A20', 'Demikian surat pernyataan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.');

                // 3. Signatures (PPK & Penyelenggara)
                $sigRow = 23;

                // Left: PPK
                $sheet->setCellValue("B{$sigRow}", 'Pejabat Pembuat Komitmen,');
                $sheet->setCellValue('B'.($sigRow + 5), $this->data['ppk']['name']);
                $sheet->getStyle('B'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('B'.($sigRow + 6), 'NIP. '.$this->data['ppk']['nip']);

                // Right: Penyelenggara
                $sheet->setCellValue("D{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue('D'.($sigRow + 1), 'Penyelenggara Kegiatan,');
                $sheet->setCellValue('D'.($sigRow + 5), $this->data['penyelenggara']['name']);
                $sheet->getStyle('D'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('D'.($sigRow + 6), 'NIP. '.$this->data['penyelenggara']['nip']);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(24);
                $sheet->getColumnDimension('C')->setWidth(30);
                $sheet->getColumnDimension('D')->setWidth(35);
            },
        ];
    }
}
