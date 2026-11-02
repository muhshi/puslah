<?php

namespace App\Filament\Resources\DailyActivityResource\Pages;

use App\Filament\Resources\DailyActivityResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDailyActivity extends CreateRecord
{
    protected static string $resource = DailyActivityResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->has('activity_date')) {
            $this->form->fill([
                'activity_date' => request()->query('activity_date'),
                'user_id' => Auth::id(),
                'status' => 'submitted',
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['user_id'])) {
            $data['user_id'] = Auth::id();
        }

        $data['source'] = 'web';

        return $data;
    }
}
