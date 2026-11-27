<?php

namespace App\Imports;

use App\Models\Survey;
use App\Models\SurveyUser;
use App\Services\PelatihanPenilaianService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PelatihanLkPenilaianImport
{
    public int $updated = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(protected Survey $survey) {}

    public function importFile(string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $service = app(PelatihanPenilaianService::class);
        $weights = $service->getWeights($this->survey);

        $surveyUsers = $this->survey->surveyUsers()
            ->with(['user.profile'])
            ->get();

        $byName = [];
        $byNipNik = [];
        foreach ($surveyUsers as $su) {
            $user = $su->user;
            if (! $user) {
                continue;
            }
            $nameKey = mb_strtolower(trim((string) ($user->profile?->full_name ?: $user->name)));
            if ($nameKey !== '') {
                $byName[$nameKey][] = $su;
            }
            foreach ([$user->profile?->nip, $user->profile?->nik, $user->profile?->old_nip] as $idVal) {
                $cleanId = trim((string) $idVal);
                if ($cleanId !== '') {
                    $byNipNik[$cleanId] = $su;
                }
            }
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, false, false);
            $headerIdx = null;
            $colMap = [];

            foreach ($rows as $idx => $row) {
                $normalized = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $row);
                if (in_array('nama', $normalized, true)) {
                    $headerIdx = $idx;
                    foreach ($normalized as $cIdx => $colName) {
                        if ($colName !== '') {
                            $colMap[$colName] = $cIdx;
                        }
                    }
                    break;
                }
            }

            if ($headerIdx === null) {
                continue;
            }

            // Map each configured weight component to its column index (matching e.g. "pre test (8%)" or "pre test")
            $componentCols = [];
            foreach ($weights as $comp) {
                $labelLower = mb_strtolower($comp['label']);
                $keyLower = mb_strtolower($comp['key']);
                foreach ($colMap as $colHeader => $cIdx) {
                    $baseHeader = trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $colHeader));
                    if ($baseHeader === $labelLower || $baseHeader === $keyLower || $colHeader === $labelLower) {
                        $componentCols[$comp['key']] = $cIdx;
                        break;
                    }
                }
            }

            $namaCol = $colMap['nama'] ?? null;
            $nipNikCol = $colMap['nip / nik'] ?? $colMap['nip_nik'] ?? $colMap['nip/nik'] ?? null;
            $nilaiAkhirCol = $colMap['nilai akhir'] ?? $colMap['nilai'] ?? null;

            for ($r = $headerIdx + 1; $r < count($rows); $r++) {
                $row = $rows[$r];
                $nama = $namaCol !== null ? trim((string) ($row[$namaCol] ?? '')) : '';
                $nipNik = $nipNikCol !== null ? trim((string) ($row[$nipNikCol] ?? '')) : '';

                if ($nama === '' && $nipNik === '') {
                    continue;
                }

                $su = null;
                if ($nipNik !== '' && isset($byNipNik[$nipNik])) {
                    $su = $byNipNik[$nipNik];
                } elseif ($nama !== '') {
                    $candidates = $byName[mb_strtolower($nama)] ?? [];
                    if (count($candidates) === 1) {
                        $su = $candidates[0];
                    } elseif (count($candidates) > 1) {
                        $this->errors[] = "Sheet {$sheet->getTitle()} baris ".($r + 1).": Nama '{$nama}' duplikat tanpa NIP/NIK.";
                        $this->skipped++;

                        continue;
                    }
                }

                if (! $su instanceof SurveyUser) {
                    $this->skipped++;

                    continue;
                }

                $nilaiKomponen = is_array($su->nilai_komponen) ? $su->nilai_komponen : [];
                $hasComponentInput = false;
                foreach ($componentCols as $compKey => $cIdx) {
                    $raw = $row[$cIdx] ?? null;
                    if ($raw !== null && $raw !== '' && is_numeric($raw)) {
                        $nilaiKomponen[$compKey] = round((float) $raw, 2);
                        $hasComponentInput = true;
                    }
                }

                $finalScore = $hasComponentInput
                    ? $service->calculateFinalScore($nilaiKomponen, $weights)
                    : null;

                if ($finalScore === null && $nilaiAkhirCol !== null) {
                    $rawFinal = $row[$nilaiAkhirCol] ?? null;
                    if ($rawFinal !== null && $rawFinal !== '' && is_numeric($rawFinal)) {
                        $finalScore = round((float) $rawFinal, 2);
                    }
                }

                if ($finalScore === null && ! $hasComponentInput) {
                    $this->skipped++;

                    continue;
                }

                $su->update([
                    'nilai_komponen' => $nilaiKomponen,
                    'score' => $finalScore !== null ? (int) round($finalScore) : $su->score,
                    'is_lulus' => true,
                ]);
                $this->updated++;
            }
        }
    }
}
