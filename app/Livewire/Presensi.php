<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Presensi extends Component
{
    public $latitude, $longitude;
    public $isWithinRadius = false;

    public function render()
    {
        $attendance = Attendance::where('user_id', Auth::user()->id)->whereDate('created_at', date('Y-m-d'))->first();
        $schedule = Schedule::where('user_id', Auth::user()->id)->first();
        return view('livewire.presensi', [
            'schedule' => $schedule,
            'isWithinRadius' => $this->isWithinRadius,
            'attendance' => $attendance,
        ]);
    }

    public function store()
    {
        $this->isWithinRadius = true;
        $this->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $schedule = Schedule::where('user_id', Auth::user()->id)->first();
        if ($schedule) {
            $attendance = Attendance::where('user_id', Auth::user()->id)->whereDate('created_at', date('Y-m-d'))->first();
            if (!$attendance) {
                $attendance = Attendance::create([
                    'user_id' => Auth::user()->id,
                    'schedule_latitude' => $schedule->office->latitude,
                    'schedule_longitude' => $schedule->office->longitude,
                    'schedule_start_time' => $schedule->shift->start_time,
                    'schedule_end_time' => $schedule->shift->end_time,
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                    'start_time' => Carbon::now('Asia/Jakarta')->toTimeString(),
                    'end_time' => Carbon::now('Asia/Jakarta')->toTimeString(),
                ]);
            } else {
                $attendance->update([
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                    'end_time' => Carbon::now('Asia/Jakarta')->toTimeString(),
                ]);
            }
            return redirect()->route('presensi', [
                'schedule' => $schedule,
                'isWithinRadius' => false,
            ]);
        }
    }
}
