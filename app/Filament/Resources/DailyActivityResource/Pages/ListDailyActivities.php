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
            Actions\Action::make('test_telegram')
                ->label('Tes Koneksi Bot 🔔')
                ->color('gray')
                ->icon('heroicon-m-bell-alert')
                ->tooltip('Kirim pesan tes ke Telegram untuk memastikan bot aktif')
                ->visible(fn () => !empty(Auth::user()?->telegram_chat_id))
                ->requiresConfirmation()
                ->modalHeading('Uji Coba Koneksi Bot Telegram')
                ->modalDescription('Sistem akan mengirimkan pesan notifikasi uji coba ke aplikasi Telegram Anda sekarang.')
                ->modalSubmitActionLabel('Kirim Pesan Tes 🚀')
                ->action(function () use ($botUsername) {
                    $currentUser = Auth::user();
                    if (!$currentUser || empty($currentUser->telegram_chat_id)) {
                        Notification::make()->warning()->title('Akun Telegram Belum Terhubung')->send();
                        return;
                    }

                    try {
                        $bot = app(\SergiX44\Nutgram\Nutgram::class);
                        $bot->sendMessage(
                            text: "🔔 *Tes Koneksi Telegram Puslah Berhasil!*\n\n" .
                                "Halo *{$currentUser->name}*, akun Puslah Anda terhubung secara aktif dengan bot @{$botUsername}.\n\n" .
                                "Mulai sekarang Anda bisa mengirimkan foto kegiatan lapangan atau catatan pekerjaan harian langsung ke bot ini kapan saja!",
                            chat_id: (int) $currentUser->telegram_chat_id,
                            parse_mode: 'Markdown'
                        );

                        Notification::make()
                            ->success()
                            ->title('Pesan Tes Berhasil Dikirim! 🔔')
                            ->body("Pesan uji coba telah terkirim ke Telegram Anda (Chat ID: {$currentUser->telegram_chat_id}). Silakan periksa aplikasi Telegram Anda.")
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Gagal Mengirim Pesan')
                            ->body('Terjadi kendala koneksi ke Telegram: ' . $e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('connect_telegram')
                ->label(fn () => !empty(Auth::user()?->telegram_chat_id) ? 'Telegram Terhubung 🟢' : 'Hubungkan Telegram 📱')
                ->color(fn () => !empty(Auth::user()?->telegram_chat_id) ? 'success' : 'primary')
                ->icon('heroicon-m-chat-bubble-left-right')
                ->modalHeading(fn () => !empty(Auth::user()?->telegram_chat_id) ? 'Status Akun Telegram Anda' : 'Hubungkan Akun Telegram')
                ->modalDescription(function () use ($botUsername) {
                    $currentUser = Auth::user();
                    if (!empty($currentUser?->telegram_chat_id)) {
                        return "Akun Telegram Anda saat ini AKTIF TERHUBUNG (Chat ID: {$currentUser->telegram_chat_id}) dengan bot @{$botUsername}. Setiap foto dan pesan yang Anda kirim ke bot akan otomatis masuk sebagai bukti dukung. Klik tombol di bawah HANYA jika Anda ingin memutuskan tautan akun.";
                    }

                    return "Kirim catatan harian dan foto dokumentasi bukti dukung dengan santai kapan saja melalui Telegram tanpa perlu buka web.";
                })
                ->modalSubmitActionLabel(fn () => !empty(Auth::user()?->telegram_chat_id) ? 'Putus Hubungan Akun' : 'Dapatkan Tautan Bot')
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
                        ->body("Klik tautan di bawah ini untuk membuka bot @{$botUsername} di Telegram Anda dan tekan Mulai/Start.")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('buka_telegram')
                                ->label('Buka Bot Telegram 🚀')
                                ->url($telegramUrl, shouldOpenInNewTab: true),
                        ])
                        ->persistent()
                        ->send();
                }),

            Actions\Action::make('my_shared_reports')
                ->label('Berkas Publik Saya 🔗')
                ->color('success')
                ->icon('heroicon-m-share')
                ->modalHeading('Daftar Berkas Publik Bukti Dukung (E-Kinerja)')
                ->modalDescription('Koleksi tautan publik resmi yang pernah Anda buat untuk dilampirkan sebagai bukti dukung di aplikasi E-Kinerja.')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn () => view('filament.pages.shared-reports-modal')),

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
