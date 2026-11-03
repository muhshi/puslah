<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SharedWorkReportResource\Pages;
use App\Models\SharedWorkReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SharedWorkReportResource extends Resource
{
    protected static ?string $model = SharedWorkReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-arrow-down';

    protected static ?string $navigationGroup = 'Manajemen Dokumen';

    protected static ?string $navigationLabel = 'Wadah Berkas KIPAPP';

    protected static ?string $modelLabel = 'Wadah Berkas KIPAPP';

    protected static ?string $pluralModelLabel = 'Wadah Berkas KIPAPP';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Butir Kinerja KIPAPP')
                    ->description('Wadah ini memiliki 1 Tautan Publik Permanen yang dapat disematkan langsung di aplikasi KIPAPP pusat.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Nama Butir Kegiatan KIPAPP')
                            ->placeholder('Contoh: Pengolahan dan Pemutakhiran Peta Wilkerstat')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('tag_slug')
                            ->label('Hashtag Telegram (#bot)')
                            ->placeholder('contoh: wilkerstat atau inovasi (tanpa tanda #)')
                            ->helperText('Kirim foto/laporan di bot Telegram dengan hashtag #wilkerstat, maka otomatis masuk ke wadah ini!')
                            ->maxLength(64)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Tautan Publik Aktif')
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('period_start')
                                    ->label('Periode Mulai (Opsional)')
                                    ->helperText('Jika diisi, kegiatan pada rentang ini otomatis masuk.'),

                                Forms\Components\DatePicker::make('period_end')
                                    ->label('Periode Selesai (Opsional)'),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Select::make('surat_tugas_id')
                            ->label('Surat Tugas Terkait (Opsional)')
                            ->relationship('suratTugas', 'nomor_surat_tugas')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Pilih jika berkas ini khusus untuk satu Surat Tugas')
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('description')
                            ->label('Uraian / Catatan Tambahan')
                            ->placeholder('Catatan ringkas atau deskripsi butir kegiatan SKP...')
                            ->rows(3)
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Butir Kegiatan KIPAPP')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (SharedWorkReport $record) => $record->description ? \Illuminate\Support\Str::limit($record->description, 50) : null),

                Tables\Columns\TextColumn::make('tag_slug')
                    ->label('Hashtag Bot')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->sortable()
                    ->visible($isSuperAdmin),

                Tables\Columns\TextColumn::make('activities_count')
                    ->label('Terkumpul')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-calendar-days')
                    ->getStateUsing(fn (SharedWorkReport $record) => $record->getActivities()->count() . ' Hari'),

                Tables\Columns\TextColumn::make('period')
                    ->label('Periode')
                    ->getStateUsing(function (SharedWorkReport $record) {
                        if ($record->period_start && $record->period_end) {
                            return $record->period_start->translatedFormat('d M') . ' - ' . $record->period_end->translatedFormat('d M Y');
                        }
                        return 'Bebas Tanggal';
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('Dilihat')
                    ->suffix('x')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('copy_link')
                    ->label('Salin Link')
                    ->icon('heroicon-m-clipboard-document')
                    ->color('success')
                    ->action(function (SharedWorkReport $record) {
                        $url = route('shared-work-report.show', ['uuid' => $record->uuid]);
                        Notification::make()
                            ->success()
                            ->title('Tautan KIPAPP Siap Disematkan!')
                            ->body("Salin URL berikut ke KIPAPP:\n\n{$url}")
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('open')
                                    ->label('Buka Halaman 🌐')
                                    ->url($url, shouldOpenInNewTab: true),
                            ])
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\Action::make('open_public')
                    ->label('Buka')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (SharedWorkReport $record) => route('shared-work-report.show', ['uuid' => $record->uuid]), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('download_docx')
                    ->label('Word')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (SharedWorkReport $record) => route('shared-work-report.download', ['uuid' => $record->uuid])),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && !$user->hasAnyRole(['super_admin', 'admin'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSharedWorkReports::route('/'),
            'create' => Pages\CreateSharedWorkReport::route('/create'),
            'edit' => Pages\EditSharedWorkReport::route('/{record}/edit'),
        ];
    }
}
