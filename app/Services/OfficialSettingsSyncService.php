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
        $userIds = array_values(array_filter([
            $state['kepala_user_id'] ?? null,
            $state['ppk_user_id'] ?? null,
            $state['bendahara_user_id'] ?? null,
        ]));

        $users = empty($userIds)
            ? collect()
            : User::with('profile')->whereIn('id', $userIds)->get()->keyBy('id');

        if (! empty($state['kepala_user_id']) && ($kepala = $users->get((int) $state['kepala_user_id']))) {
            $state['cert_signer_name'] = $kepala->profile?->full_name ?: $kepala->name;
            $state['cert_signer_nip'] = $kepala->profile?->nip ?: '-';
            if (empty($state['cert_signer_title'])) {
                $state['cert_signer_title'] = $kepala->profile?->jabatan ?: 'Kepala Badan Pusat Statistik Kabupaten Demak';
            }
        }

        if (! empty($state['ppk_user_id']) && ($ppk = $users->get((int) $state['ppk_user_id']))) {
            $state['ppk_name'] = $ppk->profile?->full_name ?: $ppk->name;
            $state['ppk_nip'] = $ppk->profile?->nip ?: '-';
            if (empty($state['ppk_title'])) {
                $state['ppk_title'] = 'Pejabat Pembuat Komitmen';
            }
        }

        if (! empty($state['bendahara_user_id']) && ($bendahara = $users->get((int) $state['bendahara_user_id']))) {
            $state['bendahara_name'] = $bendahara->profile?->full_name ?: $bendahara->name;
            $state['bendahara_nip'] = $bendahara->profile?->nip ?: '-';
            if (empty($state['bendahara_title'])) {
                $state['bendahara_title'] = 'Bendahara Pengeluaran';
            }
        }

        return $state;
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
