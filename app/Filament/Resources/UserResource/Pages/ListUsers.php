<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_mitra')
                ->label('Import Mitra')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('attachment')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'])
                        ->disk('public')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/public/' . $data['attachment']);

                    $import = new \App\Imports\UsersImport;
                    \Maatwebsite\Excel\Facades\Excel::import($import, $filePath);

                    \Filament\Notifications\Notification::make()
                        ->title('Import Mitra Completed')
                        ->body("Success: {$import->success}, Skipped: {$import->skipped}, Failed: {$import->failed}")
                        ->success()
                        ->send();
                })
                ->modalContentFooter(fn() => new \Illuminate\Support\HtmlString('
                    <div class="mt-4 text-sm text-gray-500">
                        <p>Download template: <a href="/templates/user_import_template.xlsx" target="_blank" class="text-primary-600 hover:underline">user_import_template.xlsx</a></p>
                    </div>
                ')),

            Actions\Action::make('import_pegawai')
                ->label('Import Pegawai')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('attachment')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'])
                        ->disk('public')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/public/' . $data['attachment']);

                    $import = new \App\Imports\EmployeesImport;
                    \Maatwebsite\Excel\Facades\Excel::import($import, $filePath);

                    \Filament\Notifications\Notification::make()
                        ->title('Import Pegawai Completed')
                        ->body("Success: {$import->success}, Skipped: {$import->skipped}, Failed: {$import->failed}")
                        ->success()
                        ->send();
                })
                ->modalContentFooter(fn() => new \Illuminate\Support\HtmlString('
                    <div class="mt-4 text-sm text-gray-500">
                        <p>Download template: <a href="' . route('download.employee.template') . '" target="_blank" class="text-primary-600 hover:underline">employee_import_template.xlsx</a></p>
                    </div>
                ')),

            Actions\CreateAction::make(),
        ];
    }
}
