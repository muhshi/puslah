<?php

namespace App\Exports\PelatihanSheets;

use App\Exports\PelatihanSheets\Concerns\SpjSheetHelper;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DaftarHadirSheet implements WithEvents, WithTitle
{
    use SpjSheetHelper;

    public function __construct(protected array $data) {}

    public function title(): string
    {
        return 'Daftar Hadir';
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

                $sheet->setCellValue('A2', 'DAFTAR HADIR PESERTA PELATIHAN');
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
                $sheet->setCellValue("B{$startRow}", 'Nama Peserta');
                $sheet->setCellValue("C{$startRow}", 'Asal / Instansi');
                $sheet->setCellValue("D{$startRow}", 'Peran');
                $sheet->setCellValue("E{$startRow}", 'Kelas');

                $sheet->mergeCells("A{$startRow}:A".($startRow + 1));
                $sheet->mergeCells("B{$startRow}:B".($startRow + 1));
                $sheet->mergeCells("C{$startRow}:C".($startRow + 1));
                $sheet->mergeCells("D{$startRow}:D".($startRow + 1));
                $sheet->mergeCells("E{$startRow}:E".($startRow + 1));

                if (! $isMultiDay) {
                    $sheet->setCellValue("F{$startRow}", 'Tanda Tangan');
                    $sheet->mergeCells("F{$startRow}:G{$startRow}");
                    $sheet->setCellValue('F'.($startRow + 1), 'Ganjil');
                    $sheet->setCellValue('G'.($startRow + 1), 'Genap');
                    $lastColLetter = 'G';
                } else {
                    $endColIdx = 5 + $dateCount;
                    $endColLetter = Coordinate::stringFromColumnIndex($endColIdx);
                    $sheet->setCellValue("F{$startRow}", 'Tanda Tangan Kehadiran');
                    $sheet->mergeCells("F{$startRow}:{$endColLetter}{$startRow}");

                    foreach ($dates as $idx => $d) {
                        $colIdx = 6 + $idx;
                        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                        $dFormatted = is_object($d) ? $d->translatedFormat('d/m/Y') : (string) $d;
                        $sheet->setCellValue("{$colLetter}".($startRow + 1), $dFormatted);
                    }
                    $lastColLetter = $endColLetter;
                }

                $this->applyHeaderStyle($sheet, "A{$startRow}:{$lastColLetter}".($startRow + 1));

                // 3. Table Rows
                $rowIdx = $startRow + 2;
                $globalNum = 0;

                $classes = ! empty($this->data['attendance_classes']) ? $this->data['attendance_classes'] : $this->data['all_classes'];

                foreach ($classes as $classData) {
                    foreach ($classData['rows'] as $item) {
                        $globalNum++;
                        $sheet->setCellValue("A{$rowIdx}", $globalNum);
                        $sheet->setCellValue("B{$rowIdx}", $item['nama']);
                        $sheet->setCellValue("C{$rowIdx}", $item['asal']);
                        $sheet->setCellValue("D{$rowIdx}", $item['bertugas_sebagai'] ?? $item['role_pelatihan']);
                        $sheet->setCellValue("E{$rowIdx}", $item['kelas']);

                        $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("D{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("E{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                        if (! $isMultiDay) {
                            $this->applyZigZagTtd($sheet, $rowIdx, $globalNum, 'F', 'G');
                        } else {
                            for ($dIdx = 0; $dIdx < $dateCount; $dIdx++) {
                                $cLetter = Coordinate::stringFromColumnIndex(6 + $dIdx);
                                $sheet->setCellValue("{$cLetter}{$rowIdx}", "{$globalNum}. ..........");
                                $align = ($globalNum % 2 !== 0) ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_RIGHT;
                                $sheet->getStyle("{$cLetter}{$rowIdx}")->getAlignment()->setHorizontal($align);
                            }
                        }

                        $sheet->getRowDimension($rowIdx)->setRowHeight(24);
                        $rowIdx++;
                    }
                }

                if ($globalNum === 0) {
                    $sheet->setCellValue("A{$rowIdx}", 'Belum ada peserta yang didaftarkan.');
                    $sheet->mergeCells("A{$rowIdx}:{$lastColLetter}{$rowIdx}");
                    $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $rowIdx++;
                }

                $dataEndRow = $rowIdx - 1;
                $this->applyBorders($sheet, "A{$startRow}:{$lastColLetter}{$dataEndRow}");

                // 4. Signatures
                $sigRow = $rowIdx + 2;
                $sigCol = Coordinate::stringFromColumnIndex(max(4, Coordinate::columnIndexFromString($lastColLetter) - 2));

                $sheet->setCellValue("{$sigCol}{$sigRow}", 'Demak, '.($this->data['end_date'] ? $this->data['end_date']->translatedFormat('d F Y') : date('d F Y')));
                $sheet->setCellValue("{$sigCol}".($sigRow + 1), 'Instruktur Daerah / Penanggung Jawab,');

                $sheet->setCellValue("{$sigCol}".($sigRow + 5), $this->data['first_inda']['nama'] ?? ($this->data['penyelenggara']['name'] ?? '-'));
                $sheet->getStyle("{$sigCol}".($sigRow + 5))->getFont()->setBold(true)->setUnderline(true);

                $sheet->setCellValue("{$sigCol}".($sigRow + 6), 'NIP. '.($this->data['first_inda']['nip'] ?? ($this->data['penyelenggara']['nip'] ?? '-')));

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(14);
                if (! $isMultiDay) {
                    $sheet->getColumnDimension('F')->setWidth(15);
                    $sheet->getColumnDimension('G')->setWidth(15);
                } else {
                    for ($dIdx = 0; $dIdx < $dateCount; $dIdx++) {
                        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(6 + $dIdx))->setWidth(14);
                    }
                }
            },
        ];
    }
}
