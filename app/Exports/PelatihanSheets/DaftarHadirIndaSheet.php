<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DaftarHadirIndaSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Daftar Hadir INDA';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $dates = $this->data['dates'];
                $dateCount = count($dates);
                $isMultiDay = $dateCount > 1;

                // 1. Header Information
                $sheet->setCellValue('A1', 'BADAN PUSAT STATISTIK KABUPATEN DEMAK');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                $sheet->setCellValue('A2', 'DAFTAR HADIR INSTRUKTUR DAERAH (INDA) PELATIHAN');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A3', strtoupper((string) ($this->data['survey']->name ?? 'PELATIHAN')));
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

                $sheet->setCellValue('A5', 'Kegiatan');
                $sheet->setCellValue('B5', ': '.($this->data['survey']->name ?? '-'));
                $sheet->setCellValue('A6', 'Tempat');
                $sheet->setCellValue('B6', ': '.$this->data['lokasi']);
                $sheet->setCellValue('A7', 'Waktu Pelaksanaan');
                $sheet->setCellValue('B7', ': '.$this->data['periode'].' ('.$this->data['jumlah_hari'].' Hari)');

                // 2. Table Headers
                $startRow = 9;
                $sheet->setCellValue("A{$startRow}", 'No.');
                $sheet->setCellValue("B{$startRow}", 'Nama Instruktur Daerah');
                $sheet->setCellValue("C{$startRow}", 'NIP');
                $sheet->setCellValue("D{$startRow}", 'Jabatan / Instansi');

                $sheet->mergeCells("A{$startRow}:A".($startRow + 1));
                $sheet->mergeCells("B{$startRow}:B".($startRow + 1));
                $sheet->mergeCells("C{$startRow}:C".($startRow + 1));
                $sheet->mergeCells("D{$startRow}:D".($startRow + 1));

                if (! $isMultiDay) {
                    $sheet->setCellValue("E{$startRow}", 'Tanda Tangan');
                    $sheet->mergeCells("E{$startRow}:F{$startRow}");
                    $sheet->setCellValue('E'.($startRow + 1), 'Ganjil');
                    $sheet->setCellValue('F'.($startRow + 1), 'Genap');
                    $lastColLetter = 'F';
                } else {
                    $endColIdx = 4 + $dateCount;
                    $endColLetter = Coordinate::stringFromColumnIndex($endColIdx);
                    $sheet->setCellValue("E{$startRow}", 'Tanda Tangan Kehadiran');
                    $sheet->mergeCells("E{$startRow}:{$endColLetter}{$startRow}");

                    foreach ($dates as $idx => $d) {
                        $colIdx = 5 + $idx;
                        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                        $dFormatted = is_object($d) ? $d->translatedFormat('d/m/Y') : (string) $d;
                        $sheet->setCellValue("{$colLetter}".($startRow + 1), $dFormatted);
                    }
                    $lastColLetter = $endColLetter;
                }

                $this->applyHeaderStyle($sheet, "A{$startRow}:{$lastColLetter}".($startRow + 1));

                // 3. Table Rows
                $indas = $this->data['indas'];
                if (empty($indas)) {
                    // Fallback to first Inda or Penyelenggara row so sheet is never broken
                    $indas = [[
                        'nama' => $this->data['first_inda']['nama'] ?? ($this->data['penyelenggara']['name'] ?? 'Instruktur Daerah'),
                        'nip' => $this->data['first_inda']['nip'] ?? ($this->data['penyelenggara']['nip'] ?? '-'),
                        'asal' => 'BPS Kabupaten Demak',
                    ]];
                }

                $rowIdx = $startRow + 2;
                foreach ($indas as $i => $item) {
                    $num = $i + 1;
                    $sheet->setCellValue("A{$rowIdx}", $num);
                    $sheet->setCellValue("B{$rowIdx}", $item['nama']);
                    $sheet->setCellValueExplicit("C{$rowIdx}", $item['nip'] ?? '-', DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$rowIdx}", $item['asal'] ?? 'BPS Kabupaten Demak');

                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    if (! $isMultiDay) {
                        $this->applyZigZagTtd($sheet, $rowIdx, $num, 'E', 'F');
                    } else {
                        for ($dIdx = 0; $dIdx < $dateCount; $dIdx++) {
                            $cLetter = Coordinate::stringFromColumnIndex(5 + $dIdx);
                            $sheet->setCellValue("{$cLetter}{$rowIdx}", "{$num}. ..........");
                            $align = ($num % 2 !== 0) ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_RIGHT;
                            $sheet->getStyle("{$cLetter}{$rowIdx}")->getAlignment()->setHorizontal($align);
                        }
                    }

                    $sheet->getRowDimension($rowIdx)->setRowHeight(26);
                    $rowIdx++;
                }

                $dataEndRow = $rowIdx - 1;
                $this->applyBorders($sheet, "A{$startRow}:{$lastColLetter}{$dataEndRow}");

                // 4. Signatures (Mengetahui PPK & Penyelenggara)
                $sigRow = $rowIdx + 2;
                $sheet->setCellValue("B{$sigRow}", 'Mengetahui,');
                $sheet->setCellValue('B'.($sigRow + 1), 'Pejabat Pembuat Komitmen,');
                $sheet->setCellValue('B'.($sigRow + 5), $this->data['ppk']['name']);
                $sheet->getStyle('B'.($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue('B'.($sigRow + 6), 'NIP. '.$this->data['ppk']['nip']);

                $rightCol = Coordinate::stringFromColumnIndex(max(4, Coordinate::columnIndexFromString($lastColLetter) - 1));
                $sheet->setCellValue("{$rightCol}{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue("{$rightCol}".($sigRow + 1), 'Penyelenggara Kegiatan,');
                $sheet->setCellValue("{$rightCol}".($sigRow + 5), $this->data['penyelenggara']['name']);
                $sheet->getStyle("{$rightCol}".($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->setCellValue("{$rightCol}".($sigRow + 6), 'NIP. '.$this->data['penyelenggara']['nip']);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(24);
                $sheet->getColumnDimension('D')->setWidth(26);
                if (! $isMultiDay) {
                    $sheet->getColumnDimension('E')->setWidth(16);
                    $sheet->getColumnDimension('F')->setWidth(16);
                } else {
                    for ($dIdx = 0; $dIdx < $dateCount; $dIdx++) {
                        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(5 + $dIdx))->setWidth(15);
                    }
                }
            },
        ];
    }
}
