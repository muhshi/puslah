<?php

namespace App\Filament\Resources\LaporanPerjalananDinasResource\Pages;

use App\Models\SuratTugas;
use App\Models\LaporanPerjalananDinas;
use Filament\Notifications\Notification;
use App\Filament\Resources\LaporanPerjalananDinasResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLaporanPerjalananDinas extends CreateRecord
{
    protected static string $resource = LaporanPerjalananDinasResource::class;

    public function mount(): void
    {
        parent::mount();

        $stId = request()->query('surat_tugas_id');
        if ($stId) {
            $st = SuratTugas::find($stId);
            if ($st && !LaporanPerjalananDinas::canCreateLpd($st)) {
                $max = LaporanPerjalananDinas::getMaxLpdCount($st);
                $msg = $max === 1
                    ? 'Surat Tugas ini memiliki rentang 1 hari dan laporannya sudah dibuat.'
                    : "Semua kuota ({$max} hari) pada Surat Tugas ini sudah terisi LPD.";

                Notification::make()
                    ->warning()
                    ->title('Surat Tugas Sudah Selesai')
                    ->body($msg)
                    ->persistent()
                    ->send();
            }
        }
    }
}
