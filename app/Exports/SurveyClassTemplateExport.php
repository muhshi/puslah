<?php

namespace App\Exports;

use App\Models\Survey;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveyClassTemplateExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected Survey $survey;
    protected int $rowNumber = 0;

    public function __construct(Survey $survey)
    {
        $this->survey = $survey;
    }

    public function collection()
    {
        return $this->survey->surveyUsers()
            ->with(['user.profile', 'class'])
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'Email',
            'Sobat ID',
            'Kelas',
        ];
    }

    /**
     * @param \App\Models\SurveyUser $row
     */
    public function map($row): array
    {
        $this->rowNumber++;
        $user = $row->user;
        $profile = $user?->profile;

        return [
            $this->rowNumber,
            $profile?->full_name ?: ($user?->name ?: '-'),
            $user?->email ?: '-',
            $profile?->sobat_id ?: '',
            $row->class?->name ?: '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE2E8F0'],
                ],
            ],
        ];
    }
}
