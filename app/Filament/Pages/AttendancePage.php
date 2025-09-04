<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Settings\SystemSettings;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AttendancePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Presensi Management';
    protected static ?string $title = 'Presensi';
    protected static string $view = 'filament.pages.attendance-page';

    // --- State yang dibaca Blade ---
    public ?Schedule $schedule = null;
    public ?Attendance $attendance = null;

    public float $officeLat;
    public float $officeLng;
    public int $radiusM;

    public string $workStart; // 'H:i'
    public string $workEnd;   // 'H:i'
    public array $workdays = []; // [1..7]
    public bool $isWorkday = false;

    // diisi dari JS
    public bool $isWithinRadius = false;
    public ?float $latitude = null;
    public ?float $longitude = null;

    public function mount(SystemSettings $cfg): void
    {
        $userId = Auth::id();
        $nowWib = Carbon::now('Asia/Jakarta');
        $today = $nowWib->toDateString();

        // 1) Ambil schedule user (kalau modelmu cuma 1 schedule per user, ambil yang terbaru)
        $this->schedule = Schedule::with(['office', 'shift'])
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        // 2) Tentukan sumber aturan (schedule jika ada, kalau tidak fallback ke SystemSettings)
        if ($this->schedule) {
            $this->officeLat = (float) $this->schedule->office->latitude;
            $this->officeLng = (float) $this->schedule->office->longitude;
            $this->radiusM = (int) $this->schedule->office->radius;

            // shift->start_time biasanya 'H:i:s', normalisasi ke 'H:i'
            $this->workStart = substr($this->schedule->shift->start_time, 0, 5);
            $this->workEnd = substr($this->schedule->shift->end_time, 0, 5);

            // hari kerja masih pakai default (sesuai flowmu sekarang)
            $this->workdays = $cfg->default_workdays;
        } else {
            $this->officeLat = (float) $cfg->default_office_lat;
            $this->officeLng = (float) $cfg->default_office_lng;
            $this->radiusM = (int) $cfg->default_geofence_radius_m;

            $this->workStart = $cfg->default_work_start; // 'H:i'
            $this->workEnd = $cfg->default_work_end;   // 'H:i'
            $this->workdays = $cfg->default_workdays;
        }

        // 3) Cek hari kerja
        $dow = $nowWib->isoWeekday(); // 1=Senin..7=Minggu
        $this->isWorkday = in_array($dow, $this->workdays, true);

        // 4) Ambil attendance hari ini (kalau ada)
        $this->attendance = Attendance::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->latest('id')
            ->first();
    }

    public function store(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $nowStr = $now->format('H:i');

        // Guard server-side
        if (!$this->isWorkday) {
            Notification::make()->title('Hari ini bukan hari kerja')->danger()->send();
            return;
        }

        // bypass geofence kalau WFA aktif di schedule
        $isWfa = (bool) optional($this->schedule)->is_wfa;
        if (!$isWfa && !$this->isWithinRadius) {
            Notification::make()->title('Di luar radius kantor')->danger()->send();
            return;
        }

        if (!($nowStr >= $this->workStart)) {
            Notification::make()->title('Belum masuk jam kerja')->danger()->send();
            return;
        }

        // Check-in / Check-out
        if (!$this->attendance?->start_time) {
            // Check-in
            $att = $this->attendance ?? new Attendance();
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
            Notification::make()->title('Check-in berhasil')->success()->send();
            return;
        }

        if (!$this->attendance->end_time) {
            // Check-out
            $this->attendance->end_latitude = $this->latitude;
            $this->attendance->end_longitude = $this->longitude;
            $this->attendance->end_time = $nowStr;
            $this->attendance->save();

            Notification::make()->title('Check-out berhasil')->success()->send();
            return;
        }

        Notification::make()->title('Presensi hari ini sudah lengkap')->warning()->send();
    }
}
