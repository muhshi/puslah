<?php

namespace App\Filament\Resources\DailyActivityResource\Pages;

use App\Services\DailyActivityAiService;
use Throwable;
use App\Models\DailyActivity;
use App\Filament\Resources\DailyActivityResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateDailyActivity extends CreateRecord
{
    protected static string $resource = DailyActivityResource::class;

    protected int $createdBatchCount = 1;
    protected array $batchRemainingDates = [];

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

        if (!empty($data['is_range'])) {
            $startDate = Carbon::parse($data['range_start'] ?? now())->startOfDay();
            $endDate = Carbon::parse($data['range_end'] ?? now())->startOfDay();
            $skipWeekends = (bool) ($data['skip_weekends'] ?? true);

            $period = CarbonPeriod::create($startDate, $endDate);
            $validDates = [];
            foreach ($period as $date) {
                if ($skipWeekends && ($date->isSaturday() || $date->isSunday())) {
                    continue;
                }
                $validDates[] = $date->copy();
            }

            if (empty($validDates)) {
                $validDates[] = $startDate;
            }

            $this->batchRemainingDates = array_slice($validDates, 1);
            $this->createdBatchCount = count($validDates);
            $data['activity_date'] = $validDates[0]->toDateString();
        } else {
            $this->batchRemainingDates = [];
            $this->createdBatchCount = 1;
        }

        // Auto-polish narasi AI jika final_content belum dipoles atau sama dengan catatan kasar
        $raw = trim($data['raw_content'] ?? '');
        $final = trim($data['final_content'] ?? '');
        if (!empty($raw) && (empty($final) || $final === $raw)) {
            try {
                $user = Auth::user();
                $aiService = app(DailyActivityAiService::class);
                $formal = $aiService->formalize($raw, [
                    'user_name' => $user?->name ?? 'Pegawai',
                    'tanggal' => $data['activity_date'] ?? now()->format('d F Y'),
                ]);
                $data['ai_formal_content'] = $formal;
                $data['final_content'] = $formal;
            } catch (Throwable $e) {
                Log::warning('Auto polish AI on web create: ' . $e->getMessage());
                if (empty($data['final_content'])) {
                    $data['final_content'] = $raw;
                }
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        unset(
            $data['is_range'],
            $data['range_start'],
            $data['range_end'],
            $data['skip_weekends']
        );

        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        if (!empty($this->batchRemainingDates)) {
            $this->record->load(['sharedWorkReports', 'attachments']);
            $sharedReportIds = $this->record->sharedWorkReports->pluck('id')->toArray();
            $attachments = $this->record->attachments;

            DB::transaction(function () use ($sharedReportIds, $attachments) {
                foreach ($this->batchRemainingDates as $date) {
                    /** @var DailyActivity $newRecord */
                    $newRecord = $this->record->replicate();
                    $newRecord->activity_date = $date->toDateString();
                    $newRecord->save();

                    if (!empty($sharedReportIds)) {
                        $newRecord->sharedWorkReports()->sync($sharedReportIds);
                    }

                    foreach ($attachments as $att) {
                        $newAtt = $att->replicate();
                        $newAtt->daily_activity_id = $newRecord->id;
                        if ($att->captured_at) {
                            $newAtt->captured_at = $date->copy()->setTimeFromTimeString(
                                Carbon::parse($att->captured_at)->toTimeString()
                            );
                        } else {
                            $newAtt->captured_at = $date->copy()->setTimeFromTimeString(now()->toTimeString());
                        }
                        $newAtt->save();
                    }
                }
            });
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        if ($this->createdBatchCount > 1) {
            return Notification::make()
                ->success()
                ->title("🎉 Berhasil Membuat {$this->createdBatchCount} Laporan Kegiatan!")
                ->body('Kegiatan telah otomatis dicatat untuk masing-masing hari kerja dalam rentang tanggal yang dipilih.');
        }

        return parent::getCreatedNotification();
    }
}
