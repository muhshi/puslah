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
            // 1. Status / Pengaturan Telegram (Ringkas)
            Actions\Action::make('connect_telegram')
                ->label(fn () => !empty(Auth::user()?->telegram_chat_id) ? 'Telegram 🟢' : 'Hubungkan Telegram 📱')
                ->color('gray')
                ->icon('heroicon-m-chat-bubble-left-right')
                ->modalHeading(fn () => !empty(Auth::user()?->telegram_chat_id) ? 'Status Akun Telegram' : 'Hubungkan Akun Telegram')
                ->modalDescription(function () use ($botUsername) {
                    $currentUser = Auth::user();
                    if (!empty($currentUser?->telegram_chat_id)) {
                        return "Akun Telegram Anda saat ini AKTIF TERHUBUNG (Chat ID: {$currentUser->telegram_chat_id}) dengan bot @{$botUsername}.\n\nSetiap foto/catatan yang Anda kirim akan otomatis masuk sebagai bukti dukung. Gunakan hashtag (misal: #wilkerstat) untuk otomatis masuk ke wadah KIPAPP.";
                    }

                    return "Kirim catatan harian dan foto dokumentasi bukti dukung dengan santai kapan saja melalui Telegram tanpa perlu buka web.";
                })
                ->extraModalFooterActions([
                    Actions\Action::make('ping_bot')
                        ->label('Kirim Pesan Tes 🔔')
                        ->color('info')
                        ->visible(fn () => !empty(Auth::user()?->telegram_chat_id))
                        ->action(function () use ($botUsername) {
                            $currentUser = Auth::user();
                            if (!$currentUser || empty($currentUser->telegram_chat_id)) return;

                            try {
                                $bot = app(\SergiX44\Nutgram\Nutgram::class);
                                $bot->sendMessage(
                                    text: "🔔 *Tes Koneksi Telegram Puslah Berhasil!*\n\nHalo *{$currentUser->name}*, akun Puslah Anda terhubung aktif dengan bot @{$botUsername}.",
                                    chat_id: (int) $currentUser->telegram_chat_id,
                                    parse_mode: 'Markdown'
                                );

                                Notification::make()->success()->title('Pesan Tes Terkirim! 🔔')->send();
                            } catch (\Throwable $e) {
                                Notification::make()->danger()->title('Gagal Kirim Pesan')->body($e->getMessage())->send();
                            }
                        }),
                ])
                ->modalSubmitAction(function (\Filament\Actions\StaticAction $action) {
                    $currentUser = Auth::user();
                    if (!empty($currentUser?->telegram_chat_id)) {
                        return $action
                            ->label('Putus Hubungan Akun')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Putus Hubungan Akun Telegram?')
                            ->modalDescription('Anda tidak akan lagi menerima reminder pengingat bukti dukung atau dapat mengirim laporan lewat bot Telegram. Lanjutkan?');
                    }

                    return $action
                        ->label('Dapatkan Tautan Bot')
                        ->color('primary');
                })
                ->action(function () use ($botUsername) {
                    $currentUser = Auth::user();
                    if (!$currentUser) return;

                    if (!empty($currentUser->telegram_chat_id)) {
                        $currentUser->update(['telegram_chat_id' => null, 'telegram_auth_token' => null]);
                        Notification::make()->success()->title('Hubungan Telegram Berhasil Diputus')->send();
                        return;
                    }

                    // Generate token baru
                    $token = Str::random(24);
                    $currentUser->update(['telegram_auth_token' => $token]);

                    $telegramUrl = "https://t.me/{$botUsername}?start={$token}";

                    Notification::make()
                        ->success()
                        ->title('Tautan Bot Siap!')
                        ->body("Buka bot @{$botUsername} di Telegram Anda dan tekan Mulai/Start.")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('buka_telegram')
                                ->label('Buka Bot Telegram 🚀')
                                ->url($telegramUrl, shouldOpenInNewTab: true),
                        ])
                        ->persistent()
                        ->send();
                }),

            // 2. Wadah Berkas KIPAPP (Modal Lengkap)
            Actions\Action::make('my_shared_reports')
                ->label('Wadah KIPAPP 📁')
                ->color('gray')
                ->icon('heroicon-m-folder')
                ->modalHeading('Daftar Wadah Berkas KIPAPP (E-Kinerja)')
                ->modalDescription('Koleksi tautan publik resmi yang pernah Anda buat untuk disematkan sebagai bukti dukung di aplikasi E-Kinerja.')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn () => view('filament.pages.shared-reports-modal')),

            // 3. Tambah Kegiatan (Primary)
            Actions\CreateAction::make()
                ->label('Tambah Kegiatan')
                ->icon('heroicon-m-plus'),
        ];
    }
}
