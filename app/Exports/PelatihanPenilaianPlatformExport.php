<?php

namespace App\Exports;

use App\Models\Survey;
use App\Services\PelatihanPenilaianService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PelatihanPenilaianPlatformExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @var array<int, array<string, mixed>> */
    protected array $rows;

    public function __construct(
        protected Survey $survey,
        protected ?string $kelasFilter = null,
    ) {
        $dataset = app(PelatihanPenilaianService::class)->build($this->survey, $this->kelasFilter);
        $this->rows = $dataset['rows'];
    }

    public function title(): string
    {
        return 'Penilaian Peserta';
    }

    public function headings(): array
    {
        return [
            'nip_nik',
            'nama',
            'instansi',
            'alamat',
            'tipe_peserta',
            'nilai',
            'is_lulus',
        ];
    }

    public function array(): array
    {
        return array_map(function (array $row): array {
            return [
                (string) ($row['nip_nik'] ?? ''),
                (string) ($row['nama'] ?? ''),
                (string) ($row['instansi'] ?? 'Mitra'),
                (string) ($row['alamat'] ?? ''),
                (string) ($row['tipe_peserta'] ?? 'eksternal'),
                $row['nilai'] !== null && $row['nilai'] !== '' ? (float) $row['nilai'] : '',
                1,
            ];
        }, $this->rows);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = 1 + count($this->rows);
                for ($r = 2; $r <= $lastRow; $r++) {
                    $val = (string) $sheet->getCell("A{$r}")->getValue();
                    $sheet->setCellValueExplicit("A{$r}", $val, DataType::TYPE_STRING);
                }
            },
        ];
    }
}
