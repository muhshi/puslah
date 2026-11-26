<?php

namespace App\Imports;

use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Services\SipetraUserDataService;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

class PelatihanKelasImport implements WithMultipleSheets
{
    public int $success = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /** @var array<int, string> */
    public array $errors = [];

    protected SipetraUserDataService $sipetraService;

    public function __construct(protected Survey $survey)
    {
        $this->sipetraService = app(SipetraUserDataService::class);
    }

    /**
     * Only import the first sheet ("Pembagian Kelas") so any reference sheets are ignored.
     *
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            0 => new class($this) implements OnEachRow, WithHeadingRow
            {
                public function __construct(private PelatihanKelasImport $parent) {}

                public function onRow(Row $row): void
                {
                    $this->parent->processRow($row->toArray(), $row->getIndex());
                }
            },
        ];
    }

    /**
     * Process one heading-mapped row from the class division sheet.
     *
     * @param  array<string, mixed>  $data
     */
    public function processRow(array $data, int $rowNumber = 0): void
    {
        try {
            $id = $this->cleanString($data['id'] ?? $data['survey_user_id'] ?? null);
            $email = $this->cleanString($data['email'] ?? null);
            $nikNip = $this->cleanString($data['nik_nip'] ?? $data['nik'] ?? $data['nip'] ?? null);
            $nama = $this->cleanString($data['nama'] ?? $data['name'] ?? null);
            $role = $this->extractRole($data);
            $kelas = $this->cleanString($data['kelas'] ?? null);
            $asal = $this->cleanString($data['asal'] ?? null);
            $tujuan = $this->cleanString($data['tujuan'] ?? null);

            // Completely blank trailing rows (even if pre-numbered with "No") should be ignored silently
            if ($id === null && $email === null && $nikNip === null && $nama === null && $role === null && $kelas === null) {
                return;
            }

            if ($id !== null) {
                // Backwards compatibility if an older exported file carries internal survey_user ID
                $surveyUser = SurveyUser::where('survey_id', $this->survey->id)->find($id);
                if (! $surveyUser) {
                    $this->skipped++;

                    return;
                }

                $this->applyUpdates($surveyUser, $role, $kelas, $asal, $tujuan);

                return;
            }

            [$user, $errorReason] = $this->resolveUser($email, $nikNip, $nama);
            if (! $user) {
                $prefix = $rowNumber > 0 ? "Baris {$rowNumber}: " : '';
                $this->errors[] = $prefix.$errorReason;
                $this->failed++;

                return;
            }

            // Deduplicate: if user was already added via web UI or appeared earlier in Excel, update instead of duplicating
            $existing = SurveyUser::where('survey_id', $this->survey->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $this->applyUpdates($existing, $role, $kelas, $asal, $tujuan);

                return;
            }

            $defaultRole = $user->hasRole('Mitra') ? 'PPL' : 'PML';

            SurveyUser::create([
                'survey_id' => $this->survey->id,
                'user_id' => $user->id,
                'status' => 'registered',
                'registered_at' => now(),
                'role_pelatihan' => $role ?? $defaultRole,
                'kelas' => $kelas ?? 'Kelas A',
                'asal' => $asal ?? $this->sipetraService->extractAsal(null, $user, 'Demak'),
                'tujuan' => $tujuan ?? ($this->survey->kota_kegiatan ?: 'Semarang'),
            ]);

            $this->created++;
            $this->success++;
        } catch (\Throwable $e) {
            $prefix = $rowNumber > 0 ? "Baris {$rowNumber}: " : '';
            $this->errors[] = $prefix.$e->getMessage();
            $this->failed++;
        }
    }

    private function applyUpdates(SurveyUser $surveyUser, ?string $role, ?string $kelas, ?string $asal, ?string $tujuan): void
    {
        $updates = array_filter([
            'role_pelatihan' => $role,
            'kelas' => $kelas,
            'asal' => $asal,
            'tujuan' => $tujuan,
        ], fn (?string $value) => $value !== null);

        if ($updates === []) {
            $this->skipped++;

            return;
        }

        $surveyUser->update($updates);
        $this->updated++;
        $this->success++;
    }

    /**
     * Resolve user uniquely by email, NIP/old_nip, or unambiguous exact name.
     *
     * @return array{0: ?User, 1: ?string}
     */
    private function resolveUser(?string $email, ?string $nikNip, ?string $nama): array
    {
        if ($email !== null && $email !== '-') {
            $byEmail = User::with(['profile', 'roles'])
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();

            if ($byEmail) {
                return [$byEmail, null];
            }

            return [null, "Email '{$email}' tidak ditemukan di daftar user."];
        }

        if ($nikNip !== null && $nikNip !== '-') {
            $byIdentity = User::with(['profile', 'roles'])
                ->whereHas('profile', function ($q) use ($nikNip) {
                    $q->where('nip', $nikNip)->orWhere('old_nip', $nikNip);
                })
                ->get();

            if ($byIdentity->count() === 1) {
                return [$byIdentity->first(), null];
            }

            if ($byIdentity->count() > 1) {
                return [null, "NIP '{$nikNip}' cocok dengan lebih dari 1 user; isi kolom Email."];
            }
        }

        if ($nama !== null && $nama !== '-') {
            $normalizedName = mb_strtolower($nama);
            $byName = User::with(['profile', 'roles'])
                ->where(function ($q) use ($normalizedName) {
                    $q->whereRaw('LOWER(name) = ?', [$normalizedName])
                        ->orWhereHas('profile', fn ($pq) => $pq->whereRaw('LOWER(full_name) = ?', [$normalizedName]));
                })
                ->get();

            if ($byName->count() === 1) {
                return [$byName->first(), null];
            }

            if ($byName->count() > 1) {
                return [null, "Nama '{$nama}' dimiliki {$byName->count()} user berbeda; wajib isi kolom Email."];
            }

            return [null, "Nama '{$nama}' tidak ditemukan di daftar user."];
        }

        return [null, 'Kolom Email/Nama kosong.'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractRole(array $data): ?string
    {
        foreach (['role_pelatihan', 'role_ppl_pml_inda_panitia', 'role_pplpmlindapanitia', 'role', 'peran'] as $key) {
            $cleaned = $this->cleanString($data[$key] ?? null);
            if ($cleaned !== null) {
                return $cleaned;
            }
        }

        return null;
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
