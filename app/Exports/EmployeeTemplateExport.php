<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeTemplateExport implements WithHeadings
{
    public function headings(): array
    {
        return [
            'Email',
            'Nama',
            'Jabatan',
            'NIP Lama',
            'NIP Baru',
        ];
    }
}
