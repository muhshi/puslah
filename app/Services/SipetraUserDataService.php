<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SipetraUserDataService
{
    /**
     * Batch fetch bank, account number, NIK, and address from db_sipetra.users
     * keyed by Puslah user_id. Never throws on connection failure (graceful fallback).
     *
     * @param  Collection<int, User>  $users
     * @return array<int, object>
     */
    public function fetchForUsers(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $sipetraIds = $users->pluck('sipetra_id')->filter()->unique()->values()->all();
        $emails = $users->pluck('email')->filter()->map(fn ($e) => strtolower((string) $e))->unique()->values()->all();

        if (empty($sipetraIds) && empty($emails)) {
            return [];
        }

        try {
            $rows = DB::connection('sipetra')
                ->table('users')
                ->select(['id', 'email', 'nik', 'nama_bank', 'nomor_rekening', 'nama_pemilik_rekening', 'alamat', 'nip', 'nip_baru'])
                ->where(function ($query) use ($sipetraIds, $emails) {
                    if (! empty($sipetraIds)) {
                        $query->whereIn('id', $sipetraIds);
                    }
                    if (! empty($emails)) {
                        $query->orWhereIn(DB::raw('LOWER(email)'), $emails);
                    }
                })
                ->get();

            return $this->mapRowsToUsers($users, $rows);
        } catch (\Throwable $e) {
            Log::warning('SipetraUserDataService fallback due to DB error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Match retrieved SIPETRA rows to Puslah users by sipetra_id first,
     * then fall back to case-insensitive email.
     *
     * @param  Collection<int, User>  $users
     * @param  Collection<int, object>  $sipetraRows
     * @return array<int, object>
     */
    public function mapRowsToUsers(Collection $users, Collection $sipetraRows): array
    {
        $byId = $sipetraRows->keyBy('id');
        $byEmail = $sipetraRows->keyBy(fn ($r) => strtolower((string) ($r->email ?? '')));

        $result = [];
        foreach ($users as $user) {
            $match = null;
            if ($user->sipetra_id && $byId->has($user->sipetra_id)) {
                $match = $byId->get($user->sipetra_id);
            } elseif ($user->email) {
                $match = $byEmail->get(strtolower((string) $user->email));
            }

            if ($match) {
                $result[$user->id] = $match;
            }
        }

        return $result;
    }

    /**
     * Extract asal/district from SIPETRA row or Puslah user profile.
     */
    public function extractAsal(?object $sipetraRow, ?User $user = null, string $fallback = 'Demak'): string
    {
        $raw = null;

        if ($sipetraRow) {
            $raw = $sipetraRow->asal ?? $sipetraRow->kecamatan ?? $sipetraRow->alamat ?? null;
        }

        if (empty($raw) && $user) {
            $raw = $user->profile?->address ?? $user->address ?? null;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return $fallback;
        }

        $raw = trim($raw);

        // Subdistricts of Kabupaten Demak (ordered by string length descending to prioritize specific matches)
        $knownDistricts = [
            'Karangtengah',
            'Kebonagung',
            'Karangawen',
            'Karanganyar',
            'Wonosalam',
            'Mranggen',
            'Dempet',
            'Sayung',
            'Guntur',
            'Bonang',
            'Wedung',
            'Gajah',
            'Mijen',
            'Demak',
        ];

        foreach ($knownDistricts as $district) {
            if (preg_match('/\b'.preg_quote($district, '/').'\b/i', $raw)) {
                return $district;
            }
        }

        // Pattern for "Kecamatan <Name>" or "Kec. <Name>"
        if (preg_match('/(?:Kec(?:amatan|\.)?\s+)([A-Za-z]+)/i', $raw, $matches)) {
            return ucfirst(strtolower(trim($matches[1])));
        }

        // Short clean city/district name (e.g. "Semarang", "Kudus") without street indicators
        if (strlen($raw) <= 30 && ! preg_match('/\b(jl|jalan|rt|rw|desa|kel|kelurahan|blok|gang|no)\b/i', $raw)) {
            $clean = trim($raw, " \t\n\r\0\x0B,.-");
            if ($clean !== '') {
                return $clean;
            }
        }

        return $fallback;
    }
}
