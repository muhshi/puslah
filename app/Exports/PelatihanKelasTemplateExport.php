<?php

namespace App\Exports;

use App\Models\Survey;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PelatihanKelasTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(protected Survey $survey) {}

    public function collection(): Collection
    {
        return $this->survey->surveyUsers()
            ->with(['user.profile'])
            ->get()
            ->map(function ($su) {
                return [
                    'id' => $su->id,
                    'nama' => $su->user?->profile?->full_name ?: ($su->user?->name ?? '-'),
                    'email' => $su->user?->email ?? '-',
                    'role_pelatihan' => $su->role_pelatihan ?? 'PPL',
                    'kelas' => $su->kelas ?? 'Kelas A',
                    'asal' => $su->asal ?? '',
                    'tujuan' => $su->tujuan ?? ($this->survey->kota_kegiatan ?? 'Semarang'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama',
            'Email',
            'Role (PPL/PML/Inda/Panitia)',
            'Kelas',
            'Asal',
            'Tujuan',
        ];
    }

    public function title(): string
    {
        return 'Pembagian Kelas';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
