<?php

namespace App\Filament\Resources\AttendanceRuleResource\Pages;

use App\Filament\Resources\AttendanceRuleResource;
use App\Models\AttendanceRule;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAttendanceRule extends CreateRecord
{
    protected static string $resource = AttendanceRuleResource::class;

    protected int $createdRecordsCount = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['approved_by'] = Auth::id();
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $userIds = (array) ($data['user_ids'] ?? []);
        unset($data['user_ids'], $data['survey_id']);

        if (empty($userIds) && isset($data['user_id'])) {
            $userIds = [$data['user_id']];
        }

        $records = [];
        DB::transaction(function () use ($data, $userIds, &$records) {
            foreach ($userIds as $userId) {
                $recordData = array_merge($data, [
                    'user_id' => $userId,
                ]);

                $records[] = AttendanceRule::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'type' => $data['type'],
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                    ],
                    $recordData
                );
            }
        });

        $this->createdRecordsCount = count($records);

        return $records[0] ?? AttendanceRule::create($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title("{$this->createdRecordsCount} Aturan Presensi berhasil disimpan.");
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
