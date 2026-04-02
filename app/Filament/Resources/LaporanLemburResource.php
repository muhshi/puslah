<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanLemburResource\Pages;
use App\Filament\Resources\LaporanLemburResource\RelationManagers;
use App\Models\LaporanLembur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LaporanLemburResource extends Resource
{
    protected static ?string $model = LaporanLembur::class;

    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Laporan Lembur';
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pegawai & Waktu')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Pegawai')
                            ->relationship('user', 'name')
                            ->default(fn() => auth()->id())
                            ->required()
                            ->searchable()
                            ->preload()
                            // If not admin, restrict to themselves, but let's just keep it simple or use hidden if needed.
                            ->disabled(fn() => !auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag'])),
                        Forms\Components\DatePicker::make('waktu')
                            ->label('Hari/Tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\TimePicker::make('mulai')
                            ->label('Waktu Mulai')
                            ->seconds(false)
                            ->required()
                            ->default(now()),
                        Forms\Components\TimePicker::make('selesai')
                            ->label('Waktu Selesai')
                            ->seconds(false)
                            ->required()
                            ->default(now()->addHours(2)),
                    ])->columns(2),

                Forms\Components\Section::make('Uraian Pekerjaan')
                    ->schema([
                        Forms\Components\Textarea::make('pekerjaan')
                            ->label('Uraian Pekerjaan/Output')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Tuliskan daftar pekerjaan. Jika lebih dari satu, gunakan nomor (1, 2, ...). Enter untuk baris baru.'),
                    ]),

                Forms\Components\Section::make('Dokumentasi Foto')
                    ->description('Wajib melampirkan minimal 2 foto.')
                    ->schema([
                        Forms\Components\FileUpload::make('foto_1')
                            ->label('Foto 1')
                            ->image()
                            ->directory('lembur_photos')
                            ->visibility('public')
                            ->required(),
                        Forms\Components\FileUpload::make('foto_2')
                            ->label('Foto 2')
                            ->image()
                            ->directory('lembur_photos')
                            ->visibility('public')
                            ->required(),
                        Forms\Components\FileUpload::make('foto_3')
                            ->label('Foto 3 (Opsional)')
                            ->image()
                            ->directory('lembur_photos')
                            ->visibility('public'),
                        Forms\Components\FileUpload::make('foto_4')
                            ->label('Foto 4 (Opsional)')
                            ->image()
                            ->directory('lembur_photos')
                            ->visibility('public'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('waktu')
                    ->label('Hari/Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mulai')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('selesai')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (LaporanLembur $record) {
                        return $record->status === 'pending' && auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']);
                    })
                    ->action(fn(LaporanLembur $record) => $record->update(['status' => 'approved'])),
                Tables\Actions\Action::make('word')
                    ->label('Download Word')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function (LaporanLembur $record) {
                        $settings = app(\App\Settings\SystemSettings::class);
                        $templatePath = $settings->laporan_lembur_template_path;
            
                        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
                            \Filament\Notifications\Notification::make()
                                ->title('Template Laporan Lembur belum diupload di Pengaturan Sistem')
                                ->danger()
                                ->send();
                            return;
                        }

                        $template = new \PhpOffice\PhpWord\TemplateProcessor(storage_path('app/public/' . $templatePath));

                        // Base Variables
                        $waktuFormat = \Carbon\Carbon::parse($record->waktu)->locale('id')->translatedFormat('l, d F Y');
                        $mulaiFormat = \Carbon\Carbon::parse($record->mulai)->format('H:i');
                        $selesaiFormat = \Carbon\Carbon::parse($record->selesai)->format('H:i');
                        
                        $template->setValue('waktu', $waktuFormat);
                        $template->setValue('nama_pegawai', $record->user->profile->full_name ?? $record->user->name);
                        $template->setValue('mulai', $mulaiFormat);
                        $template->setValue('selesai', $selesaiFormat);
                        
                        // Newlines for text
                        // PhpWord needs line breaks replaced with <w:br/>
                        $pekerjaanFormatted = str_replace("\n", '</w:t><w:br/><w:t>', htmlspecialchars($record->pekerjaan));
                        $template->setValue('pekerjaan', $pekerjaanFormatted);
                        
                        // Pictures
                        for ($i = 1; $i <= 4; $i++) {
                            $fotoField = "foto_{$i}";
                            if ($record->{$fotoField} && file_exists(storage_path('app/public/' . $record->{$fotoField}))) {
                                $template->setImageValue($fotoField, [
                                    'path' => storage_path('app/public/' . $record->{$fotoField}),
                                    'width' => 250,
                                    'height' => 250,
                                    'ratio' => true
                                ]);
                            } else {
                                $template->setValue($fotoField, '');
                            }
                        }

                        $waktuFile = \Carbon\Carbon::parse($record->waktu)->format('Ymd');
                        $namaFile = "Laporan_Lembur_" . str_replace(' ', '_', $record->user->name) . "_{$waktuFile}.docx";
                        $tempPath = storage_path('app/temp_' . $namaFile);
                        $template->saveAs($tempPath);
                        
                        return response()->download($tempPath)->deleteFileAfterSend();
                    }),
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
        $user = auth()->user();

        // Admin/Atasan can see all, regular user only theirs
        if ($user && !$user->hasRole(['super_admin', 'Kasubag', 'Kepala'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLaporanLemburs::route('/'),
            'create' => Pages\CreateLaporanLembur::route('/create'),
            'edit' => Pages\EditLaporanLembur::route('/{record}/edit'),
        ];
    }
}
