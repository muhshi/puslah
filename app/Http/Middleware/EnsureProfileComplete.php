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

        // izinkan logout supaya nggak nge-loop
        if ($request->routeIs('logout') || $request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        $p = $user->profile;
        $complete = $p && $p->full_name && $p->birth_date && $p->address && $p->phone;

        if (!$complete) {
            $sipetraUrl = rtrim(config('services.sipetra.base_url', 'https://bpsdemak.com'), '/') . '/account';
            return redirect()->away($sipetraUrl);
        }

        return $next($request);
    }
}
