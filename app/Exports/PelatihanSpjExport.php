<?php

namespace App\Exports;

use App\Exports\PelatihanSheets\BuktiPenerimaanUangSheet;
use App\Exports\PelatihanSheets\DaftarHadirIndaSheet;
use App\Exports\PelatihanSheets\DaftarHadirSheet;
use App\Exports\PelatihanSheets\LampiranLpdSheet;
use App\Exports\PelatihanSheets\TandaTerimaAtkSheet;
use App\Models\Survey;
use App\Services\PelatihanSpjDataBuilder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PelatihanSpjExport implements WithMultipleSheets
{
    protected Survey $survey;

    protected array $data;

    public function __construct(Survey $survey)
    {
        $this->survey = $survey;
        $this->data = app(PelatihanSpjDataBuilder::class)->build($survey);
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        $sheets = [
            new DaftarHadirSheet($this->data),
            new DaftarHadirIndaSheet($this->data),
            new BuktiPenerimaanUangSheet($this->data),
            new LampiranLpdSheet($this->data),
        ];

        // Conditional 5th sheet only when include_atk is true
        if (! empty($this->data['include_atk'])) {
            $sheets[] = new TandaTerimaAtkSheet($this->data);
        }

        return $sheets;
    }
}
