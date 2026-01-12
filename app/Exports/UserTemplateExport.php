<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;

class UserTemplateExport implements WithHeadings
{
    public function headings(): array
    {
        return [
            'Nama',
            'Email',
            'Alamat',
            'No HP',
        ];
    }
}
