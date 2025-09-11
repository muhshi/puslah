<?php

namespace App\Filament\Pages;

use App\Models\SurveyUser;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MySurveys extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Pengaturan Akun';
    protected static ?string $title = 'Survei Saya';
    protected static string $view = 'filament.pages.my-surveys';

    public function getViewData(): array
    {
        $rows = SurveyUser::with(['survey'])
            ->where('user_id', Auth::id())
            ->latest()->get();

        return compact('rows');
    }
}
