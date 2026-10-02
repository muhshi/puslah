<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class AttendanceUnattendedRecap extends Component
{
    public string $date = '';
    public ?int $surveyId = null;
    public string $userType = 'all'; // 'all', 'organik', 'mitra'
    public string $statusFilter = 'unattended'; // 'all', 'unattended', 'leave'
    public string $search = '';

    public function mount(?int $surveyId = null, ?string $date = null): void
    {
        $this->date = $date ?: Carbon::today('Asia/Jakarta')->toDateString();
        $this->surveyId = $surveyId;
    }

    public static function formatWaNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        $clean = preg_replace('/[^\d]/', '', $phone);
        if (empty($clean)) {
            return null;
        }
        $clean = ltrim($clean, '0');
        if (!str_starts_with($clean, '62')) {
            $clean = '62' . $clean;
        }
        return $clean;
    }

    public function getActiveSurveysProperty()
    {
        return Survey::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getRecapDataProperty(): array
    {
        $date = $this->date ?: Carbon::today('Asia/Jakarta')->toDateString();

        if ($this->surveyId) {
            $suIds = SurveyUser::where('survey_id', $this->surveyId)->pluck('user_id');
            $stIds = SuratTugas::where('survey_id', $this->surveyId)->pluck('user_id');
            $targetUserIds = $suIds->merge($stIds)->unique()->filter()->values();
        } else {
            $activeSurveyIds = Survey::where('is_active', true)->pluck('id');
            $suIds = SurveyUser::whereIn('survey_id', $activeSurveyIds)->pluck('user_id');
            $stIds = SuratTugas::whereIn('survey_id', $activeSurveyIds)->pluck('user_id');
            $surveyUserIds = $suIds->merge($stIds);

            $organikIds = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['Organik', 'Kepala', 'Kasubag', 'Ketua Tim', 'Operator', 'IPDS', 'Pengolahan']);
            })->pluck('id');

            $targetUserIds = $surveyUserIds->merge($organikIds)->unique()->filter()->values();
        }

        if ($targetUserIds->isEmpty()) {
            return [
                'total_target' => 0,
                'attended_count' => 0,
                'leave_count' => 0,
                'unattended_count' => 0,
                'items' => collect(),
                'copy_text' => '',
            ];
        }

        $baseUsersQuery = User::with(['profile', 'roles'])
            ->whereIn('users.id', $targetUserIds)
            ->whereDoesntHave('profile', fn($q) => $q->where('employment_status', 'nonaktif'));

        if ($this->userType === 'organik') {
            $baseUsersQuery->whereHas('roles', fn($q) => $q->whereIn('name', ['Organik', 'Kepala', 'Kasubag', 'Ketua Tim', 'Operator', 'IPDS', 'Pengolahan']));
        } elseif ($this->userType === 'mitra') {
            $baseUsersQuery->whereHas('roles', fn($q) => $q->where('name', 'Mitra'));
        }

        $allTargetUsers = $baseUsersQuery->orderBy('name')->get();
        $targetIds = $allTargetUsers->pluck('id');

        $attendedUserIds = Attendance::whereDate('created_at', $date)
            ->whereIn('user_id', $targetIds)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $leaveUsers = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereIn('user_id', $targetIds)
            ->pluck('reason', 'user_id')
            ->all();

        $attendedCount = count($attendedUserIds);
        $totalTarget = $allTargetUsers->count();

        // Hanya yang belum presensi
        $unattendedUsers = $allTargetUsers->reject(fn($u) => in_array($u->id, $attendedUserIds));

        // Mapping data peserta
        $processed = $unattendedUsers->map(function ($u) use ($leaveUsers, $date) {
            $isOnLeave = isset($leaveUsers[$u->id]);
            $leaveReason = $isOnLeave ? $leaveUsers[$u->id] : null;
            $rawPhone = $u->profile?->phone;
            $waPhone = self::formatWaNumber($rawPhone);

            $activeSurveys = Survey::where('is_active', true)
                ->where(function ($q) use ($u) {
                    $q->whereHas('surveyUsers', fn($sq) => $sq->where('user_id', $u->id))
                        ->orWhereHas('suratTugas', fn($sq) => $sq->where('user_id', $u->id));
                })
                ->pluck('name')
                ->all();

            $dateFormatted = Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM Y');
            $presensiUrl = url('/presensi');
            $waMessage = "Halo {$u->name}, kami dari BPS Kabupaten Demak mengingatkan untuk melakukan presensi Puslah pada hari ini ({$dateFormatted}): {$presensiUrl} . Terima kasih 🙏";

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $rawPhone,
                'wa_phone' => $waPhone,
                'wa_url' => $waPhone ? "https://wa.me/{$waPhone}?text=" . urlencode($waMessage) : null,
                'roles' => $u->roles->pluck('name')->join(', '),
                'jabatan' => $u->profile?->jabatan ?: $u->roles->pluck('name')->join(', '),
                'is_leave' => $isOnLeave,
                'leave_reason' => $leaveReason,
                'active_surveys' => $activeSurveys,
            ];
        });

        $leaveCount = $processed->where('is_leave', true)->count();
        $unattendedWithoutLeaveCount = $processed->where('is_leave', false)->count();

        if ($this->statusFilter === 'unattended') {
            $filtered = $processed->where('is_leave', false);
        } elseif ($this->statusFilter === 'leave') {
            $filtered = $processed->where('is_leave', true);
        } else {
            $filtered = $processed;
        }

        if (!empty($this->search)) {
            $searchTerm = strtolower($this->search);
            $filtered = $filtered->filter(function ($item) use ($searchTerm) {
                return str_contains(strtolower($item['name']), $searchTerm)
                    || str_contains(strtolower($item['phone'] ?? ''), $searchTerm)
                    || str_contains(strtolower($item['jabatan'] ?? ''), $searchTerm);
            });
        }

        $surveyName = $this->surveyId ? Survey::find($this->surveyId)?->name : 'Seluruh Kegiatan Aktif / Pegawai';
        $tglIndo = Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM Y');
        $presensiUrl = url('/presensi');

        $copyLines = [];
        $copyLines[] = "📢 *PENGINGAT PRESENSI PUSLAH BPS KABUPATEN DEMAK*";
        $copyLines[] = "📅 *Hari/Tanggal:* {$tglIndo}";
        $copyLines[] = "📋 *Kegiatan:* {$surveyName}";
        $copyLines[] = "";
        $copyLines[] = "Berikut daftar rekan yang *belum melakukan presensi* hari ini:";

        $counter = 1;
        foreach ($processed->where('is_leave', false) as $row) {
            $phoneStr = $row['phone'] ? " ({$row['phone']})" : "";
            $copyLines[] = "{$counter}. {$row['name']}{$phoneStr}";
            $counter++;
        }

        if ($counter === 1) {
            $copyLines[] = "*(Alhamdulillah, semua rekan sudah melakukan presensi / sedang cuti)*";
        }

        $copyLines[] = "";
        $copyLines[] = "Total belum presensi: " . ($counter - 1) . " orang.";
        $copyLines[] = "Bagi rekan-rekan di atas, mohon segera melakukan presensi melalui tautan:";
        $copyLines[] = "🔗 {$presensiUrl}";
        $copyLines[] = "";
        $copyLines[] = "Terima kasih atas kerjasamanya 🙏";

        $copyText = implode("\n", $copyLines);

        return [
            'total_target' => $totalTarget,
            'attended_count' => $attendedCount,
            'leave_count' => $leaveCount,
            'unattended_count' => $unattendedWithoutLeaveCount,
            'items' => $filtered->values(),
            'copy_text' => $copyText,
        ];
    }

    public function exportCsv()
    {
        $data = $this->getRecapDataProperty();
        $items = $data['items'];
        $dateStr = $this->date ?: date('Y-m-d');
        $filename = "rekap-belum-presensi-{$dateStr}.csv";

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['No', 'Nama', 'Jabatan / Role', 'No HP', 'Status', 'Kegiatan']);
            $no = 1;
            foreach ($items as $item) {
                fputcsv($out, [
                    $no++,
                    $item['name'],
                    $item['jabatan'],
                    $item['phone'] ?? '-',
                    $item['is_leave'] ? 'Sedang Cuti (' . $item['leave_reason'] . ')' : 'Belum Presensi',
                    implode('; ', $item['active_surveys']),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.attendance-unattended-recap', [
            'recap' => $this->getRecapDataProperty(),
            'activeSurveys' => $this->getActiveSurveysProperty(),
        ]);
    }
}
