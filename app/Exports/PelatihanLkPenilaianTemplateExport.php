<?php

namespace App\Exports;

use App\Models\Survey;
use App\Services\PelatihanPenilaianService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PelatihanLkPenilaianTemplateExport implements WithMultipleSheets
{
    public function __construct(
        protected Survey $survey,
        protected ?string $kelasFilter = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        $dataset = app(PelatihanPenilaianService::class)->build($this->survey, $this->kelasFilter);
        $weights = $dataset['weights'];
        $rows = $dataset['rows'];
        $classOfficials = $dataset['class_officials'];

        $grouped = collect($rows)->groupBy('kelas');
        if ($grouped->isEmpty()) {
            $fallbackClass = $this->kelasFilter ?: ($dataset['classes'][0] ?? 'Kelas A');
            $grouped = collect([$fallbackClass => collect()]);
        }

        $sheets = [];
        foreach ($grouped as $kelasNama => $classRows) {
            $officials = $classOfficials[$kelasNama] ?? ['inda' => [], 'panitia' => []];
            $sheets[] = new class(
                $this->survey,
                (string) $kelasNama,
                $officials,
                $weights,
                $classRows->values()->all(),
            ) implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
            {
                /**
                 * @param  array{inda?: array<int, string>, panitia?: array<int, string>}  $officials
                 * @param  array<int, array{key: string, label: string, weight: float|int}>  $weights
                 * @param  array<int, array<string, mixed>>  $classRows
                 */
                public function __construct(
                    protected Survey $survey,
                    protected string $kelasNama,
                    protected array $officials,
                    protected array $weights,
                    protected array $classRows,
                ) {}

                public function title(): string
                {
                    return substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $this->kelasNama) ?: 'Kelas', 0, 31);
                }

                public function array(): array
                {
                    $indaText = ! empty($this->officials['inda']) ? implode(', ', $this->officials['inda']) : '-';
                    $panitiaText = ! empty($this->officials['panitia']) ? implode(', ', $this->officials['panitia']) : '-';

                    $out = [
                        ['LEMBAR KERJA PENILAIAN PESERTA PELATIHAN'],
                        ['Nama Pelatihan', $this->survey->name],
                        ['Kelas Pelatihan', $this->kelasNama],
                        ['Nama Inda Kelas', $indaText],
                        ['Nama Manajer Kelas / Panitia', $panitiaText],
                        [''],
                    ];

                    $headers = ['No', 'NIP / NIK', 'Nama', 'Instansi', 'Alamat', 'Tipe Peserta', 'Role'];
                    foreach ($this->weights as $comp) {
                        $w = rtrim(rtrim(number_format((float) $comp['weight'], 2, '.', ''), '0'), '.');
                        $headers[] = "{$comp['label']} ({$w}%)";
                    }
                    $headers[] = 'Nilai Akhir';
                    $headers[] = 'is_lulus';
                    $out[] = $headers;

                    $firstCompColIdx = 8; // Column H (1-based)
                    foreach ($this->classRows as $idx => $row) {
                        $excelRowNum = 8 + $idx;
                        $line = [
                            $idx + 1,
                            (string) ($row['nip_nik'] ?? ''),
                            (string) ($row['nama'] ?? ''),
                            (string) ($row['instansi'] ?? 'Mitra'),
                            (string) ($row['alamat'] ?? ''),
                            (string) ($row['tipe_peserta'] ?? 'eksternal'),
                            (string) ($row['role'] ?? 'PPL'),
                        ];

                        $formulaParts = [];
                        foreach ($this->weights as $cIdx => $comp) {
                            $val = $row['nilai_komponen'][$comp['key']] ?? null;
                            $line[] = $val !== null && $val !== '' ? (float) $val : '';
                            $colLetter = Coordinate::stringFromColumnIndex($firstCompColIdx + $cIdx);
                            $ratio = ((float) $comp['weight']) / 100.0;
                            $formulaParts[] = "({$colLetter}{$excelRowNum}*{$ratio})";
                        }

                        $line[] = count($formulaParts) > 0
                            ? '='.implode('+', $formulaParts)
                            : ($row['nilai'] ?? '');
                        $line[] = 1;

                        $out[] = $line;
                    }

                    return $out;
                }

                public function columnFormats(): array
                {
                    return [
                        'B' => NumberFormat::FORMAT_TEXT,
                    ];
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true, 'size' => 13]],
                        2 => ['font' => ['bold' => true]],
                        3 => ['font' => ['bold' => true]],
                        4 => ['font' => ['bold' => true]],
                        5 => ['font' => ['bold' => true]],
                        7 => [
                            'font' => ['bold' => true],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                        ],
                    ];
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event): void {
                            $sheet = $event->sheet->getDelegate();
                            $lastColIdx = 9 + count($this->weights);
                            $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);
                            $lastRow = max(7, 7 + count($this->classRows));

                            // Force NIP/NIK cells to explicit string so leading digits/16-18 digits stay intact
                            for ($r = 8; $r <= $lastRow; $r++) {
                                $val = (string) $sheet->getCell("B{$r}")->getValue();
                                $sheet->setCellValueExplicit("B{$r}", $val, DataType::TYPE_STRING);
                            }

                            $sheet->getStyle("A7:{$lastCol}{$lastRow}")->applyFromArray([
                                'borders' => [
                                    'allBorders' => [
                                        'borderStyle' => Border::BORDER_THIN,
                                        'color' => ['rgb' => 'CBD5E1'],
                                    ],
                                ],
                            ]);
                        },
                    ];
                }
            };
        }

        return $sheets;
    }
}
