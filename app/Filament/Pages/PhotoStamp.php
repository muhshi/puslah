<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class PhotoStamp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationGroup = 'Alat Bantu';
    protected static ?string $title = 'Stempel Foto';
    protected static ?string $navigationLabel = 'Stempel Foto';
    protected static ?string $slug = 'stempel-foto';
    protected static string $view = 'filament.pages.photo-stamp';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Admin/super_admin role bisa akses
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        // User spesifik bisa akses
        if ($user->email === 'abdul.muhshi@bps.go.id') {
            return true;
        }

        return false;
    }
}
