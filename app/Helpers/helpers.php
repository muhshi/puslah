<?php

use Illuminate\Support\Facades\Auth;

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        // super admin
        return Auth::user()->roles[0]->name == 'super_admin';
    }
}
