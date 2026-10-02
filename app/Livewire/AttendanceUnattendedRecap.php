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
    public string $activeTab = 'all'; // 'all', 'belum_datang', 'belum_pulang', 'lengkap'
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

        // Jika waktu sekarang >= 15:00 WIB, default tab otomatis ke 'belum_pulang' bila ada yang belum pulang
        $currentHour = (int) Carbon::now('Asia/Jakarta')->format('H');
        if ($currentHour >= 15) {
            $this->activeTab = 'belum_pulang';
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
                'sudah_datang_count' => 0,
                'belum_datang_count' => 0,
                'belum_pulang_count' => 0,
                'lengkap_count' => 0,
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
                'sudah_datang_count' => 0,
                'belum_datang_count' => 0,
                'belum_pulang_count' => 0,
                'lengkap_count' => 0,
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

            if ($leave) {
                $status = 'cuti';
            } elseif (!$att) {
                $status = 'belum_datang';
            } elseif (empty($att->end_time)) {
                $status = 'belum_pulang';
            } else {
                $status = 'lengkap';
            }

            // Pesan WA disesuaikan apakah belum datang atau belum pulang
            if ($status === 'belum_datang') {
                $waMessage = "Halo {$p->name}, kami dari BPS Kabupaten Demak mengingatkan untuk segera melakukan presensi datang hari ini ({$tglIndo}) melalui aplikasi Puslah: {$presensiUrl} . Terima kasih 🙏";
            } elseif ($status === 'belum_pulang') {
                $waMessage = "Halo {$p->name}, kami dari BPS Kabupaten Demak mengingatkan untuk jangan lupa melakukan presensi pulang hari ini ({$tglIndo}) melalui aplikasi Puslah: {$presensiUrl} . Terima kasih 🙏";
            } else {
                $waMessage = "";
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $rawPhone,
                'wa_phone' => $waPhone,
                'wa_url' => ($waPhone && $waMessage) ? "https://wa.me/{$waPhone}?text=" . urlencode($waMessage) : null,
                'status' => $status,
                'check_in_time' => $att?->start_time,
                'check_out_time' => $att?->end_time,
                'leave_reason' => $leave?->reason,
            ];
        });

        $totalPetugas = $mapped->count();
        $belumDatangCount = $mapped->where('status', 'belum_datang')->count();
        $belumPulangCount = $mapped->where('status', 'belum_pulang')->count();
        $lengkapCount = $mapped->where('status', 'lengkap')->count();
        $sudahDatangCount = $mapped->whereIn('status', ['belum_pulang', 'lengkap'])->count();
        $cutiCount = $mapped->where('status', 'cuti')->count();

        // Filter tab
        $filtered = match ($this->activeTab) {
            'belum_datang' => $mapped->where('status', 'belum_datang'),
            'belum_pulang' => $mapped->where('status', 'belum_pulang'),
            'lengkap' => $mapped->where('status', 'lengkap'),
            default => $mapped,
        };

        // Filter search
        if (!empty($this->search)) {
            $term = strtolower($this->search);
            $filtered = $filtered->filter(fn($i) => str_contains(strtolower($i['name']), $term) || str_contains(strtolower($i['phone'] ?? ''), $term));
        }

        // Teks pengingat untuk copas ke WA group
        $copyLines = [];
        if ($this->activeTab === 'belum_pulang' || ($this->activeTab === 'all' && (int) Carbon::now('Asia/Jakarta')->format('H') >= 15)) {
            // Format Pengingat Pulang
            $copyLines[] = "📢 *PENGINGAT PRESENSI PULANG*";
            $copyLines[] = "📋 *Kegiatan:* {$survey->name}";
            $copyLines[] = "📅 *Hari/Tanggal:* {$tglIndo}";
            $copyLines[] = "";

            $belumPulangList = $mapped->where('status', 'belum_pulang')->values();
            if ($belumPulangList->isEmpty()) {
                $copyLines[] = "✅ *Alhamdulillah, seluruh petugas sudah melakukan presensi pulang hari ini.*";
            } else {
                $copyLines[] = "Berikut daftar rekan petugas yang *belum presensi pulang*:";
                foreach ($belumPulangList as $idx => $row) {
                    $phoneStr = $row['phone'] ? " ({$row['phone']})" : "";
                    $copyLines[] = ($idx + 1) . ". {$row['name']}{$phoneStr}";
                }
                $copyLines[] = "";
                $copyLines[] = "Total belum presensi pulang: " . $belumPulangList->count() . " orang.";
                $copyLines[] = "Mohon rekan yang bersangkutan untuk melakukan presensi pulang melalui:";
                $copyLines[] = "🔗 {$presensiUrl}";
            }
        } else {
            // Format Pengingat Datang
            $copyLines[] = "📢 *PENGINGAT PRESENSI DATANG*";
            $copyLines[] = "📋 *Kegiatan:* {$survey->name}";
            $copyLines[] = "📅 *Hari/Tanggal:* {$tglIndo}";
            $copyLines[] = "";

            $belumDatangList = $mapped->where('status', 'belum_datang')->values();
            if ($belumDatangList->isEmpty()) {
                $copyLines[] = "✅ *Alhamdulillah, semua petugas sudah melakukan presensi datang hari ini.*";
            } else {
                $copyLines[] = "Berikut daftar rekan petugas yang *belum presensi datang*:";
                foreach ($belumDatangList as $idx => $row) {
                    $phoneStr = $row['phone'] ? " ({$row['phone']})" : "";
                    $copyLines[] = ($idx + 1) . ". {$row['name']}{$phoneStr}";
                }
                $copyLines[] = "";
                $copyLines[] = "Total belum presensi datang: " . $belumDatangList->count() . " orang.";
                $copyLines[] = "Mohon segera melakukan presensi melalui:";
                $copyLines[] = "🔗 {$presensiUrl}";
            }
        }
        $copyLines[] = "";
        $copyLines[] = "Terima kasih atas kerjasamanya 🙏";

        return [
            'survey_name' => $survey->name,
            'total_petugas' => $totalPetugas,
            'sudah_datang_count' => $sudahDatangCount,
            'belum_datang_count' => $belumDatangCount,
            'belum_pulang_count' => $belumPulangCount,
            'lengkap_count' => $lengkapCount,
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
