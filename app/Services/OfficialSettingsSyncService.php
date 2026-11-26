<?php

namespace App\Services;

use App\Models\User;

class OfficialSettingsSyncService
{
    /**
     * Resolve official data (name, nip, title) from user IDs while preserving
     * manual title overrides or fallback values when no user_id is selected.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function buildSyncedAttributes(array $state): array
    {
        $officialMap = [
            'kepala_user_id' => [
                'name' => 'cert_signer_name',
                'nip' => 'cert_signer_nip',
                'title' => 'cert_signer_title',
                'default_title' => fn (User $user): string => $user->profile?->jabatan ?: 'Kepala Badan Pusat Statistik Kabupaten Demak',
            ],
            'ppk_user_id' => [
                'name' => 'ppk_name',
                'nip' => 'ppk_nip',
                'title' => 'ppk_title',
                'default_title' => 'Pejabat Pembuat Komitmen',
            ],
            'bendahara_user_id' => [
                'name' => 'bendahara_name',
                'nip' => 'bendahara_nip',
                'title' => 'bendahara_title',
                'default_title' => 'Bendahara Pengeluaran',
            ],
            'kasubag_umum_user_id' => [
                'name' => 'kasubag_umum_name',
                'nip' => 'kasubag_umum_nip',
                'title' => 'kasubag_umum_title',
                'default_title' => 'Kepala Subbagian Umum',
            ],
        ];

        $userIds = array_values(array_filter(array_map(
            fn (string $key) => $state[$key] ?? null,
            array_keys($officialMap)
        )));

        $users = empty($userIds)
            ? collect()
            : User::with('profile')->whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($officialMap as $userField => $target) {
            $userId = $state[$userField] ?? null;
            if (empty($userId) || ! ($user = $users->get((int) $userId))) {
                continue;
            }

            $state[$target['name']] = self::stripAcademicTitles($user->profile?->full_name ?: $user->name);
            $state[$target['nip']] = $user->profile?->nip ?: '-';

            if (empty($state[$target['title']])) {
                $defaultTitle = $target['default_title'];
                $state[$target['title']] = is_callable($defaultTitle) ? $defaultTitle($user) : $defaultTitle;
            }
        }

        return $state;
    }

    /**
     * Strip trailing comma-separated academic degrees/titles from an official's name.
     */
    public static function stripAcademicTitles(?string $name): string
    {
        $clean = trim((string) $name);
        if ($clean === '' || $clean === '-') {
            return $clean ?: '-';
        }

        $withoutSuffix = preg_replace('/\s*,.*$/', '', $clean);

        return trim((string) $withoutSuffix) ?: $clean;
    }

    /**
     * Options array of internal (non-Mitra) employees for Filament Select dropdowns.
     *
     * @return array<int, string>
     */
    public static function getEmployeeOptions(): array
    {
        return User::with('profile')
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', 'Mitra'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (User $user): array {
                $displayName = $user->profile?->full_name ?: $user->name;
                $nip = $user->profile?->nip ?: '-';

                return [$user->id => "{$displayName} (NIP. {$nip})"];
            })
            ->toArray();
    }
}
