<?php

namespace App\Filament\Resources\PelatihanResource\Pages;

use App\Exports\PelatihanSpjExport;
use App\Filament\Resources\PelatihanResource;
use App\Services\PelatihanUndanganService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class EditPelatihan extends EditRecord
{
    protected static string $resource = PelatihanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('downloadUndanganHeader')
                    ->label('Download Undangan (.docx)')
                    ->icon('heroicon-o-document-text')
                    ->action(fn () => app(PelatihanUndanganService::class)->downloadUndanganWord($this->record)),

                Action::make('downloadSpjHeader')
                    ->label('Download Paket SPJ (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => Excel::download(
                        new PelatihanSpjExport($this->record),
                        'Paket_SPJ_'.Str::slug($this->record->name ?: 'Pelatihan').'.xlsx'
                    )),

                Action::make('cetakDaftarHadirHeader')
                    ->label('Cetak Daftar Hadir (PDF)')
                    ->icon('heroicon-o-printer')
                    ->action(fn () => response()->streamDownload(
                        fn () => print (app(PelatihanUndanganService::class)->renderDaftarHadirPdf($this->record)->output()),
                        'Daftar_Hadir_'.Str::slug($this->record->name ?: 'Pelatihan').'.pdf',
                        ['Content-Type' => 'application/pdf']
                    )),
            ])
                ->label('Dokumen Pelatihan')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->button(),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
