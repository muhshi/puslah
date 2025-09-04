<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Schedule;
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

    // ====== State aturan presensi (dibaca Blade) ======
    public ?Schedule $schedule = null;
    public ?Attendance $attendance = null;

    public float $officeLat = 0.0;
    public float $officeLng = 0.0;
    public int $radiusM = 0;

    public string $workStart = '06:00'; // 'H:i'
    public string $workEnd = '16:00';   // 'H:i'
    public array $workdays = [];        // [1..7]
    public bool $isWorkday = false;

    public function render()
    {
        // Tidak perlu query ulang—public properties otomatis tersedia di Blade.
        return view('livewire.presensi');
    }

    // --- INIT: ambil schedule kalau ada, kalau tidak fallback ke SystemSettings ---
    public function mount(SystemSettings $cfg): void
    {
        $userId = Auth::id();
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        // 1) Load schedule terbaru (atau sesuaikan kalau kamu sudah punya kolom date)
        $this->schedule = Schedule::with(['office', 'shift'])
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        // 2) Tentukan sumber aturan
        if ($this->schedule) {
            $this->officeLat = (float) $this->schedule->office->latitude;
            $this->officeLng = (float) $this->schedule->office->longitude;
            $this->radiusM = (int) $this->schedule->office->radius;

            $this->workStart = substr($this->schedule->shift->start_time, 0, 5);
            $this->workEnd = substr($this->schedule->shift->end_time, 0, 5);

            // sementara hari kerja masih global
            $this->workdays = $cfg->default_workdays;
        } else {
            $this->officeLat = (float) $cfg->default_office_lat;
            $this->officeLng = (float) $cfg->default_office_lng;
            $this->radiusM = (int) $cfg->default_geofence_radius_m;

            $this->workStart = $cfg->default_work_start; // 'H:i'
            $this->workEnd = $cfg->default_work_end;   // 'H:i'
            $this->workdays = $cfg->default_workdays;
        }

        // 3) Hari kerja?
        $this->isWorkday = in_array(Carbon::now('Asia/Jakarta')->isoWeekday(), $this->workdays, true);

        // 4) Attendance hari ini (kalau ada)
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

        // Cek cuti
        $approvedLeave = Leave::where('user_id', Auth::id())
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        if ($approvedLeave) {
            session()->flash('error', 'Anda sedang cuti. Tidak bisa presensi.');
            return redirect('presensi');
        }

        // Geofence: bypass kalau WFA
        $isWfa = (bool) optional($this->schedule)->is_wfa;
        if (!$isWfa && !$this->isWithinRadius) {
            session()->flash('error', 'Di luar radius kantor.');
            return redirect('presensi');
        }

        $nowStr = Carbon::now('Asia/Jakarta')->format('H:i');

        //(opsional) validasi jam kerja dasar
        // if ($nowStr < $this->workStart) {
        //     session()->flash('error', 'Belum masuk jam kerja.');
        //     return redirect('presensi');
        // }

        // Attendance hari ini
        if (!$this->attendance) {
            // === Klik pertama → CHECK-IN ===
            $att = new Attendance();
            $att->user_id = Auth::id();

            if ($this->schedule) {
                $att->schedule_latitude = $this->officeLat;
                $att->schedule_longitude = $this->officeLng;
                $att->schedule_start_time = $this->workStart;
                $att->schedule_end_time = $this->workEnd;
            } else {
                // fallback SystemSettings (sudah diset ke $officeLat/$officeLng dst)
                $att->schedule_latitude = $this->officeLat;
                $att->schedule_longitude = $this->officeLng;
                $att->schedule_start_time = $this->workStart;
                $att->schedule_end_time = $this->workEnd;
            }

            $att->start_latitude = $this->latitude;
            $att->start_longitude = $this->longitude;
            $att->start_time = $nowStr;

            $att->save();
            $this->attendance = $att;
        } else {
            // === Klik ke-2/3/dst → CHECK-OUT (replace) ===
            $this->attendance->end_latitude = $this->latitude;
            $this->attendance->end_longitude = $this->longitude;
            $this->attendance->end_time = $nowStr;
            $this->attendance->save();
        }

        $isWfa = (bool) optional($this->schedule)->is_wfa;
        if (!$isWfa) {
            $dist = $this->distanceMeters(
                $this->latitude,
                $this->longitude,
                $this->officeLat,
                $this->officeLng
            );
            if ($dist > $this->radiusM) {
                session()->flash('error', 'Di luar radius kantor.');
                return redirect('presensi');
            }
        }

        $dist = $this->distanceMeters($this->latitude, $this->longitude, $this->officeLat, $this->officeLng);
        if ($dist > $this->radiusM) {
            session()->flash('error', 'Di luar radius kantor.');
            return redirect('presensi');
        }

        return redirect('admin/attendances');
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000; // meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }
}

