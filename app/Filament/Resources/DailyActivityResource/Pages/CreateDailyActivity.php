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

        // Auto-polish narasi AI jika final_content belum dipoles atau sama dengan catatan kasar
        $raw = trim($data['raw_content'] ?? '');
        $final = trim($data['final_content'] ?? '');
        if (!empty($raw) && (empty($final) || $final === $raw)) {
            try {
                $user = Auth::user();
                $aiService = app(\App\Services\DailyActivityAiService::class);
                $formal = $aiService->formalize($raw, [
                    'user_name' => $user?->name ?? 'Pegawai',
                    'tanggal' => $data['activity_date'] ?? now()->format('d F Y'),
                ]);
                $data['ai_formal_content'] = $formal;
                $data['final_content'] = $formal;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto polish AI on web create: ' . $e->getMessage());
                if (empty($data['final_content'])) {
                    $data['final_content'] = $raw;
                }
            }
        }

        return $data;
    }
}
