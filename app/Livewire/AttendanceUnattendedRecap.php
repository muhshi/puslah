<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Survey;
use Carbon\Carbon;
use Livewire\Component;

class AttendanceUnattendedRecap extends Component
{
    public string $date = '';
    public ?int $surveyId = null;
    public string $activeTab = 'all'; // 'all', 'sudah', 'belum'
    public string $search = '';

    public function mount(?int $surveyId = null, ?string $date = null): void
    {
        $this->date = $date ?: Carbon::today('Asia/Jakarta')->toDateString();

        if ($surveyId) {
            $this->surveyId = $surveyId;
        } else {
            // Default ke survey aktif yang memiliki peserta dan presensi hari ini
            $today = $this->date;
            $activeWithAttendance = Survey::where('is_active', true)
                ->whereHas('participants', function ($q) use ($today) {
                    $q->whereHas('attendances', fn($aq) => $aq->whereDate('created_at', $today));
                })
                ->latest('id')
                ->first();

            $this->surveyId = $activeWithAttendance?->id
                ?? Survey::where('name', 'like', '%Pengolahan Pemutakhiran Kerangka Geospasial%')->value('id')
                ?? Survey::where('is_active', true)->has('participants')->latest('id')->value('id');
        }
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
            ->has('participants')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getRecapDataProperty(): array
    {
        $date = $this->date ?: Carbon::today('Asia/Jakarta')->toDateString();

        if (!$this->surveyId) {
            return [
                'survey_name' => '-',
                'total_petugas' => 0,
                'sudah_count' => 0,
                'belum_count' => 0,
                'cuti_count' => 0,
                'items' => collect(),
                'copy_text' => '',
            ];
        }

        $survey = Survey::with(['participants.profile'])->find($this->surveyId);

        if (!$survey) {
            return [
                'survey_name' => '-',
                'total_petugas' => 0,
                'sudah_count' => 0,
                'belum_count' => 0,
                'cuti_count' => 0,
                'items' => collect(),
                'copy_text' => '',
            ];
        }

        $participants = $survey->participants()->orderBy('name')->get();
        $participantIds = $participants->pluck('id')->all();

        // Ambil data presensi hari ini untuk peserta
        $attendances = Attendance::whereIn('user_id', $participantIds)
            ->whereDate('created_at', $date)
            ->get()
            ->keyBy('user_id');

        // Ambil data cuti hari ini untuk peserta
        $leaves = Leave::where('status', 'approved')
            ->whereIn('user_id', $participantIds)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get()
            ->keyBy('user_id');

        $tglIndo = Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM Y');
        $presensiUrl = url('/presensi');

        $mapped = $participants->map(function ($p) use ($attendances, $leaves, $tglIndo, $presensiUrl) {
            $att = $attendances->get($p->id);
            $leave = $leaves->get($p->id);
            $rawPhone = $p->profile?->phone;
            $waPhone = self::formatWaNumber($rawPhone);

            $status = 'belum';
            if ($att) {
                $status = 'sudah';
            } elseif ($leave) {
                $status = 'cuti';
            }

            $waMessage = "Halo {$p->name}, kami dari BPS Kabupaten Demak mengingatkan untuk segera melakukan presensi hari ini ({$tglIndo}) melalui aplikasi Puslah: {$presensiUrl} . Terima kasih 🙏";

            return [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $rawPhone,
                'wa_phone' => $waPhone,
                'wa_url' => $waPhone ? "https://wa.me/{$waPhone}?text=" . urlencode($waMessage) : null,
                'status' => $status,
                'check_in_time' => $att?->start_time,
                'check_out_time' => $att?->end_time,
                'leave_reason' => $leave?->reason,
            ];
        });

        $totalPetugas = $mapped->count();
        $sudahCount = $mapped->where('status', 'sudah')->count();
        $belumCount = $mapped->where('status', 'belum')->count();
        $cutiCount = $mapped->where('status', 'cuti')->count();

        // Filter tab
        $filtered = match ($this->activeTab) {
            'sudah' => $mapped->where('status', 'sudah'),
            'belum' => $mapped->where('status', 'belum'),
            default => $mapped,
        };

        // Filter search
        if (!empty($this->search)) {
            $term = strtolower($this->search);
            $filtered = $filtered->filter(fn($i) => str_contains(strtolower($i['name']), $term) || str_contains(strtolower($i['phone'] ?? ''), $term));
        }

        // Teks pengingat untuk copas ke WA group
        $copyLines = [];
        $copyLines[] = "📢 *PENGINGAT PRESENSI PETUGAS*";
        $copyLines[] = "📋 *Kegiatan:* {$survey->name}";
        $copyLines[] = "📅 *Hari/Tanggal:* {$tglIndo}";
        $copyLines[] = "";

        $belumList = $mapped->where('status', 'belum')->values();
        if ($belumList->isEmpty()) {
            $copyLines[] = "✅ *Alhamdulillah, semua petugas sudah melakukan presensi hari ini.*";
        } else {
            $copyLines[] = "Berikut daftar rekan petugas yang *belum presensi*:";
            foreach ($belumList as $idx => $row) {
                $phoneStr = $row['phone'] ? " ({$row['phone']})" : "";
                $copyLines[] = ($idx + 1) . ". {$row['name']}{$phoneStr}";
            }
            $copyLines[] = "";
            $copyLines[] = "Total belum presensi: " . $belumList->count() . " orang.";
            $copyLines[] = "Mohon rekan yang bersangkutan untuk segera melakukan presensi melalui:";
            $copyLines[] = "🔗 {$presensiUrl}";
        }
        $copyLines[] = "";
        $copyLines[] = "Terima kasih atas kerjasamanya 🙏";

        return [
            'survey_name' => $survey->name,
            'total_petugas' => $totalPetugas,
            'sudah_count' => $sudahCount,
            'belum_count' => $belumCount,
            'cuti_count' => $cutiCount,
            'items' => $filtered->values(),
            'copy_text' => implode("\n", $copyLines),
        ];
    }

    public function render()
    {
        return view('livewire.attendance-unattended-recap', [
            'recap' => $this->getRecapDataProperty(),
            'activeSurveys' => $this->getActiveSurveysProperty(),
        ]);
    }
}
