<?php

namespace App\Exports;

use App\Models\User;
use App\Services\SipetraUserDataService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PelatihanReferensiUserSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Referensi Mitra & Pegawai';
    }

    public function collection(): Collection
    {
        $users = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Mitra', 'Organik']))
            ->with(['roles', 'profile'])
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        $sipetraService = app(SipetraUserDataService::class);
        $sipetraMap = $sipetraService->fetchForUsers($users);

        return $users->map(function (User $user) use ($sipetraService, $sipetraMap): array {
            $sipetra = $sipetraMap[$user->id] ?? null;
            $roleSistem = $user->roles->pluck('name')->intersect(['Mitra', 'Organik'])->implode(', ');
            $nikOrNip = ($sipetra?->nik ?? null)
                ?: ($user->profile?->nip ?: ($user->profile?->old_nip ?: '-'));
            $asal = $sipetraService->extractAsal($sipetra, $user, '') ?: '-';

            return [
                'nama' => $user->profile?->full_name ?: $user->name,
                'email' => $user->email,
                'role_sistem' => $roleSistem ?: '-',
                'nik_nip' => $nikOrNip,
                'kecamatan_asal' => $asal,
                'jabatan' => $user->profile?->jabatan ?: '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Nama',
            'Email (Copy ke Sheet Pembagian Kelas)',
            'Role Sistem',
            'NIK / NIP',
            'Kecamatan / Asal',
            'Jabatan',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
