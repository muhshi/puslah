<?php

namespace App\Filament\Resources\PelatihanResource\Pages;

use App\Exports\PelatihanSpjExport;
use App\Filament\Resources\PelatihanResource;
use App\Filament\Resources\SuratTugasResource;
use App\Services\PelatihanUndanganService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
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
                Action::make('cetakBundelSpjPdfHeader')
                    ->label('Cetak Bundel SPJ Kelas (PDF)')
                    ->icon('heroicon-o-document-duplicate')
                    ->schema(function (): array {
                        $classes = app(PelatihanUndanganService::class)->getAvailableClasses($this->record);
                        if (count($classes) <= 1) {
                            return [];
                        }

                        $options = ['__ALL_ZIP__' => 'Semua Kelas (ZIP: 1 PDF per Kelas)'];
                        foreach ($classes as $kelas) {
                            $options[$kelas] = "{$kelas} (1 PDF Lengkap)";
                        }

                        return [
                            Select::make('kelas')
                                ->label('Pilih Kelas yang Dicetak')
                                ->options($options)
                                ->default('__ALL_ZIP__')
                                ->required(),
                        ];
                    })
                    ->action(fn (array $data) => app(PelatihanUndanganService::class)
                        ->downloadBundelSpjKelas($this->record, $data['kelas'] ?? null)),

                Action::make('downloadUndanganHeader')
                    ->label('Download Undangan (.docx)')
                    ->icon('heroicon-o-document-text')
                    ->action(fn () => app(PelatihanUndanganService::class)->downloadUndanganWord($this->record)),

                Action::make('downloadSpjHeader')
                    ->label('Download Rekap SPJ (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => Excel::download(
                        new PelatihanSpjExport($this->record),
                        'Rekap_SPJ_'.Str::slug($this->record->name ?: 'Pelatihan').'.xlsx'
                    )),

                Action::make('cetakDaftarHadirHeader')
                    ->label('Cetak Daftar Hadir (PDF)')
                    ->icon('heroicon-o-printer')
                    ->action(fn () => response()->streamDownload(
                        fn () => print (app(PelatihanUndanganService::class)->renderDaftarHadirPdf($this->record)->output()),
                        'Daftar_Hadir_'.Str::slug($this->record->name ?: 'Pelatihan').'.pdf',
                        ['Content-Type' => 'application/pdf']
                    )),

                Action::make('cetakPernyataanRiilHeader')
                    ->label('Cetak Pernyataan Riil (PDF)')
                    ->icon('heroicon-o-document-check')
                    ->action(fn () => response()->streamDownload(
                        fn () => print (app(PelatihanUndanganService::class)->renderPernyataanRiilPdf($this->record)->output()),
                        'Pernyataan_Riil_'.Str::slug($this->record->name ?: 'Pelatihan').'.pdf',
                        ['Content-Type' => 'application/pdf']
                    )),

                Action::make('cetakPernyataanTidakKendaraanHeader')
                    ->label('Cetak Surat Pernyataan Tdk Kendaraan (PDF)')
                    ->icon('heroicon-o-truck')
                    ->action(fn () => response()->streamDownload(
                        fn () => print (app(PelatihanUndanganService::class)->renderPernyataanTidakKendaraanPdf($this->record)->output()),
                        'Surat_Pernyataan_Tidak_Kendaraan_'.Str::slug($this->record->name ?: 'Pelatihan').'.pdf',
                        ['Content-Type' => 'application/pdf']
                    )),
            ])
                ->label('Dokumen Pelatihan')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->button(),

            Action::make('buatSuratTugasHeader')
                ->label('Buat Surat Tugas')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(fn (): bool => SuratTugasResource::canCreate())
                ->url(fn (): string => SuratTugasResource::getUrl('create-bulk', ['survey_id' => $this->record->id])),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
