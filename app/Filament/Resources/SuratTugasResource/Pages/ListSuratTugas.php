<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use App\Models\SuratTugas;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuratTugas extends ListRecords
{
    protected static string $resource = SuratTugasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_surat_tugas')
                ->label('Import Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\Select::make('survey_id')
                        ->label('Pilih Survey')
                        ->options(function () {
                            return \App\Models\Survey::where('is_active', true)
                                ->orderByDesc('created_at')
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (\Filament\Forms\Set $set, $state) {
                            if ($state) {
                                $survey = \App\Models\Survey::find($state);
                                if ($survey) {
                                    $set('keperluan', "{$survey->name}");
                                    if ($survey->start_date) {
                                        $set('waktu_mulai', \Carbon\Carbon::parse($survey->start_date)->format('Y-m-d'));
                                    }
                                    if ($survey->end_date) {
                                        $set('waktu_selesai', \Carbon\Carbon::parse($survey->end_date)->format('Y-m-d'));
                                    }
                                }
                            }
                        })
                        ->required(),
                    \Filament\Forms\Components\Group::make()->schema([
                        \Filament\Forms\Components\DatePicker::make('tanggal')
                            ->label('Tanggal Surat')
                            ->default(now()->format('Y-m-d'))
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('waktu_mulai')
                            ->label('Mulai')
                            ->default(now()->format('Y-m-d')),
                        \Filament\Forms\Components\DatePicker::make('waktu_selesai')
                            ->label('Selesai')
                            ->default(now()->format('Y-m-d')),
                    ])->columns(3),
                    \Filament\Forms\Components\Textarea::make('keperluan')
                        ->label('Keperluan')
                        ->required(),
                    \Filament\Forms\Components\FileUpload::make('file_excel')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'])
                        ->directory('imports')
                        ->required()
                        ->helperText(new \Illuminate\Support\HtmlString('Download template: <a href="/templates/surat_tugas_import_template.xlsx" target="_blank" class="text-primary-600 hover:underline">surat_tugas_import_template.xlsx</a>')),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/public/' . $data['file_excel']);
                    $import = new \App\Imports\SuratTugasImport(
                        $data['survey_id'],
                        $data['tanggal'],
                        $data['waktu_mulai'],
                        $data['waktu_selesai'],
                        $data['keperluan']
                    );
                    \Maatwebsite\Excel\Facades\Excel::import($import, $filePath);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Import Selesai')
                        ->body("Berhasil: {$import->success}, Dilewati: {$import->skipped}, Gagal: {$import->failed}")
                        ->success()
                        ->send();
                }),
            Actions\Action::make('create-bulk')
                ->label('Buat Kolektif')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(fn() => SuratTugasResource::getUrl('create-bulk')),
            Actions\Action::make('manage-blocked-numbers')
                ->label('Block Nomor')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->url(fn() => SuratTugasResource::getUrl('manage-blocked-numbers')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SuratTugasResource\Widgets\SkippedNumbersInfoWidget::class,
        ];
    }
}
