<?php

namespace App\Filament\Resources\SuratUndanganResource\Pages;

use App\Filament\Resources\SuratUndanganResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSuratUndangan extends EditRecord
{
    protected static string $resource = SuratUndanganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->url(fn () => route('surat-undangan.download-pdf', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('download_docx')
                ->label('Download Word (DOCX)')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(fn () => route('surat-undangan.download-docx', $this->record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
