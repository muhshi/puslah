<?php

namespace App\Exports;

use App\Models\Survey;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PelatihanKelasWorkbookExport implements WithMultipleSheets
{
    public function __construct(protected Survey $survey) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new PelatihanKelasTemplateExport($this->survey),
            new PelatihanReferensiUserSheet,
        ];
    }
}
