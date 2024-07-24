<?php

namespace App\Livewire;

use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Presensi extends Component
{
    public function render()
    {
        return view('livewire.presensi', [
            'schedule' => Schedule::where('user_id', Auth::user()->id)->first(),
        ]);
    }
}
