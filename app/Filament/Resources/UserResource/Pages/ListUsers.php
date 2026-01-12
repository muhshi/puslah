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
            Actions\Action::make('import')
                ->label('Import Users')
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
                        ->title('Import Completed')
                        ->body("Success: {$import->success}, Skipped: {$import->skipped}, Failed: {$import->failed}")
                        ->success()
                        ->send();
                })
                ->modalContentFooter(fn() => new \Illuminate\Support\HtmlString('
                    <div class="mt-4 text-sm text-gray-500">
                        <p>Download template: <a href="/templates/user_import_template.xlsx" target="_blank" class="text-primary-600 hover:underline">user_import_template.xlsx</a></p>
                    </div>
                ')),
            Actions\CreateAction::make(),
        ];
    }
}
