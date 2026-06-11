<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Headers
$sheet->setCellValue('A1', 'email_petugas');
$sheet->setCellValue('B1', 'jabatan_tugas');
$sheet->setCellValue('C1', 'tempat_tugas');

// Set dummy data
$sheet->setCellValue('A2', 'andi@bps.go.id');
$sheet->setCellValue('B2', 'PPL');
$sheet->setCellValue('C2', 'Kecamatan Demak');

$sheet->setCellValue('A3', 'budi@bps.go.id');
$sheet->setCellValue('B3', 'PML');
$sheet->setCellValue('C3', 'Kecamatan Karanganyar');

// Auto size columns
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);
$sheet->getColumnDimension('C')->setAutoSize(true);

$writer = new Xlsx($spreadsheet);
$writer->save(__DIR__ . '/../public/templates/surat_tugas_import_template.xlsx');

echo "Template generated successfully.\n";
