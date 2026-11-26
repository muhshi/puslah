<?php

namespace App\Exports\PelatihanSheets\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait SpjSheetHelper
{
    /**
     * Apply standard thin borders to a cell range.
     */
    protected function applyBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
    }

    /**
     * Apply standard table header styling (bold, centered, light gray background).
     */
    protected function applyHeaderStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Calibri',
                'size' => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEFEFEF'],
            ],
        ]);
    }

    /**
     * Place alternating (zig-zag) signature cell for SPJ tables.
     */
    protected function applyZigZagTtd(Worksheet $sheet, int $row, int $number, string $colOdd, string $colEven): void
    {
        if ($number % 2 !== 0) {
            $sheet->setCellValue("{$colOdd}{$row}", "{$number}. ..........");
            $sheet->getStyle("{$colOdd}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } else {
            $sheet->setCellValue("{$colEven}{$row}", "{$number}. ..........");
            $sheet->getStyle("{$colEven}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    /**
     * Set row height for clean printing.
     */
    protected function setRowHeights(Worksheet $sheet, int $startRow, int $endRow, float $height = 24): void
    {
        for ($r = $startRow; $r <= $endRow; $r++) {
            $sheet->getRowDimension($r)->setRowHeight($height);
        }
    }
}
