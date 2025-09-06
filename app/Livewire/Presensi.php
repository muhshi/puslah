<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\AttendanceRule;
use App\Settings\SystemSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Presensi extends Component
{
    // ====== State dari JS ======
    public ?float $latitude = null;
    public ?float $longitude = null;
    public bool $isWithinRadius = false;

    // ====== State aturan presensi (buat Blade) ======
    public ?Schedule $schedule = null;
    public ?Attendance $attendance = null;

    public float $officeLat = 0.0;
    public float $officeLng = 0.0;
    public int $radiusM = 0;
    public int $effectiveRadiusM = 0;

    public string $workStart = '06:00'; // 'H:i'
    public string $workEnd = '16:00'; // 'H:i'
    public array $workdays = [];      // [1..7]
    public bool $isWorkday = false;

    // Rules (hasil evaluasi)
    public bool $ruleWfa = false;
    public bool $ruleBanned = false;
    public ?int $ruleRadiusOverride = null;

    // UI state
    public bool $hasLocation = false;      // tombol submit muncul hanya setelah tag lokasi
    public ?string $uiWarning = null;      // warning geolocation / di luar radius

    public string $defaultOfficeName = 'BPS Kabupaten Demak';

    public function render()
    {
        return view('livewire.presensi');
    }

    public function mount(SystemSettings $cfg): void
    {
        $userId = Auth::id();
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        // 1) Ambil schedule terbaru (kalau masih dipakai)
        $this->schedule = Schedule::with(['office', 'shift'])
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        // 2) Sumber aturan lokasi & jam
        if ($this->schedule) {
            $this->officeLat = (float) $this->schedule->office->latitude;
            $this->officeLng = (float) $this->schedule->office->longitude;
            $this->radiusM = (int) $this->schedule->office->radius;

            $this->workStart = substr($this->schedule->shift->start_time, 0, 5);
            $this->workEnd = substr($this->schedule->shift->end_time, 0, 5);
            $this->workdays = $cfg->default_workdays;
        } else {
            $this->officeLat = (float) $cfg->default_office_lat;
            $this->officeLng = (float) $cfg->default_office_lng;
            $this->radiusM = (int) $cfg->default_geofence_radius_m;

            $this->workStart = $cfg->default_work_start;
            $this->workEnd = $cfg->default_work_end;
            $this->workdays = $cfg->default_workdays;
            $this->defaultOfficeName = $cfg->default_office_name ?? 'BPS Kabupaten Demak';
        }

        // 3) Evaluasi AttendanceRule aktif hari ini (WFA/BANNED)
        $activeRules = AttendanceRule::where('user_id', $userId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        $this->ruleBanned = (bool) $activeRules->firstWhere('type', 'BANNED');
        $wfaRule = $activeRules->firstWhere('type', 'WFA');
        $this->ruleWfa = (bool) $wfaRule;
        $this->ruleRadiusOverride = $wfaRule?->radius_override_m;

        // 4) Radius efektif
        $this->effectiveRadiusM = $this->ruleRadiusOverride ?? $this->radiusM;

        // 5) Hari kerja (kalau mau dipakai)
        $this->isWorkday = in_array(Carbon::now('Asia/Jakarta')->isoWeekday(), $this->workdays, true);

        // 6) Attendance hari ini
        $this->attendance = Attendance::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->latest('id')
            ->first();
    }

    public function store(SystemSettings $cfg)
    {
        $this->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $today = Carbon::today('Asia/Jakarta')->toDateString();

        // Cuti?
        $approvedLeave = Leave::where('user_id', Auth::id())
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        if ($approvedLeave) {
            session()->flash('error', 'Anda sedang cuti. Tidak bisa presensi.');
            return redirect('presensi');
        }

        // BANNED?
        if ($this->ruleBanned) {
            session()->flash('error', 'Anda diblokir presensi pada periode ini.');
            return redirect('presensi');
        }

        // Geofence (server authority) — bypass jika WFA
        if (!$this->ruleWfa) {
            $dist = $this->distanceMeters(
                $this->latitude,
                $this->longitude,
                $this->officeLat,
                $this->officeLng
            );
            $radius = $this->effectiveRadiusM ?: $this->radiusM;
            if ($dist > $radius) {
                session()->flash('error', 'Di luar radius kantor.');
                return redirect('presensi');
            }
        }

        $nowStr = Carbon::now('Asia/Jakarta')->format('H:i');

        // Check-in / Check-out
        if (!$this->attendance) {
            // CHECK-IN
            $att = new Attendance();
            $att->user_id = Auth::id();

            $att->schedule_latitude = $this->officeLat;
            $att->schedule_longitude = $this->officeLng;
            $att->schedule_start_time = $this->workStart;
            $att->schedule_end_time = $this->workEnd;

            $att->start_latitude = $this->latitude;
            $att->start_longitude = $this->longitude;
            $att->start_time = $nowStr;

            $att->save();
            $this->attendance = $att;
        } else {
            // CHECK-OUT (replace)
            $this->attendance->end_latitude = $this->latitude;
            $this->attendance->end_longitude = $this->longitude;
            $this->attendance->end_time = $nowStr;
            $this->attendance->save();
        }

        return redirect('admin/attendances');
    }

    // Haversine (meter)
    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }
}
