<?php

use Illuminate\Support\Facades\Auth;

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        // super admin
        return Auth::user()->roles[0]->name == 'super_admin';
    }
}

if (!function_exists('isPegawai')) {
    function isPegawai(): bool
    {
        return Auth::user()->roles[0]->name == 'Pegawai BPS';
    }
}

if (!function_exists('normalizePhoneNumber')) {
    function normalizePhoneNumber($phone)
    {
        if (!$phone) {
            return null;
        }

        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 62, replace with 0
        if (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }
}
