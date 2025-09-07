<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return $next($request);
        }

        // izinkan akses ke halaman profil & logout supaya nggak nge-loop
        if ($request->routeIs('filament.admin.pages.profile-me') || $request->routeIs('logout')) {
            return $next($request);
        }

        $p = $user->profile;
        $complete = $p && $p->full_name && $p->birth_date && $p->address && $p->phone;

        if (!$complete) {
            return redirect()->route('filament.admin.pages.profile-me')
                ->with('message', 'Lengkapi profil Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
