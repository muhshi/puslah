<?php

namespace App\Filament\Resources\DailyActivityResource\Pages;

use App\Filament\Resources\DailyActivityResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ListDailyActivities extends ListRecords
{
    protected static string $resource = DailyActivityResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\DailyActivityCalendarWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $isConnected = !empty($user?->telegram_chat_id);
        $botUsername = config('nutgram.bot_username') ?: env('TELEGRAM_BOT_USERNAME') ?: 'dinamit3321_bot';

        return [
            Actions\Action::make('connect_telegram')
                ->label($isConnected ? 'Telegram Terhubung ✅' : 'Hubungkan Telegram 📱')
                ->color($isConnected ? 'success' : 'primary')
                ->icon('heroicon-m-chat-bubble-left-right')
                ->modalHeading('Integrasi Telegram Bot Bukti Dukung')
                ->modalDescription(function () use ($user, $isConnected, $botUsername) {
                    if ($isConnected) {
                        return "Akun Telegram Anda saat ini sudah TERHUBUNG (Chat ID: {$user->telegram_chat_id}). Anda bisa langsung mengirimkan foto dan catatan santai ke bot @" . $botUsername . ".";
                    }

                    return "Kirim catatan harian dan foto dokumentasi bukti dukung dengan santai kapan saja melalui Telegram tanpa perlu buka web.";
                })
                ->modalSubmitActionLabel($isConnected ? 'Putus Hubungan Akun' : 'Hubungkan Sekarang')
                ->action(function () use ($user, $isConnected, $botUsername) {
                    if ($isConnected) {
                        $user->update(['telegram_chat_id' => null, 'telegram_auth_token' => null]);
                        Notification::make()->success()->title('Hubungan Telegram Diputus')->send();
                        return;
                    }

                    // Generate token baru
                    $token = Str::random(24);
                    $user->update(['telegram_auth_token' => $token]);

                    $telegramUrl = "https://t.me/{$botUsername}?start={$token}";

                    Notification::make()
                        ->success()
                        ->title('Tautan Dibuat!')
                        ->body("Klik tautan berikut untuk membuka bot di Telegram dan klik Mulai/Start.")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('buka_telegram')
                                ->label('Buka Bot Telegram 🚀')
                                ->url($telegramUrl, shouldOpenInNewTab: true),
                        ])
                        ->persistent()
                        ->send();
                }),

            Actions\Action::make('export_monthly_docx')
                ->label('Unduh Rekap Word 📄')
                ->color('info')
                ->icon('heroicon-m-document-arrow-down')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('start_date')
                        ->label('Dari Tanggal')
                        ->default(Carbon::now()->startOfMonth())
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('Sampai Tanggal')
                        ->default(Carbon::now()->endOfMonth())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $user = Auth::user();
                    $startDate = Carbon::parse($data['start_date'])->startOfDay();
                    $endDate = Carbon::parse($data['end_date'])->endOfDay();

                    $activities = \App\Models\DailyActivity::query()
                        ->where('user_id', $user->id)
                        ->whereBetween('activity_date', [$startDate, $endDate])
                        ->with(['suratTugas', 'attachments'])
                        ->orderBy('activity_date', 'asc')
                        ->get();

                    if ($activities->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('Tidak Ada Kegiatan')
                            ->body('Tidak ditemukan catatan kegiatan pada rentang tanggal yang dipilih.')
                            ->send();
                        return;
                    }

                    try {
                        $generator = app(\App\Services\WorkReportGeneratorService::class);
                        $period = $startDate->translatedFormat('d M Y') . ' s.d. ' . $endDate->translatedFormat('d M Y');
                        $res = $generator->generateWordReport($user, $activities, $period);

                        return response()->download($res['path'], $res['name']);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Gagal Membuat Rekap Word')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\CreateAction::make()
                ->label('Tambah Kegiatan'),
        ];
    }
}
