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
                            ->disabled(fn() => !auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']))
                            ->dehydrated(),
                        Forms\Components\DatePicker::make('waktu')
                            ->label('Hari/Tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\TimePicker::make('mulai')
                            ->label('Waktu Mulai')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->required()
                            ->default(now()),
                        Forms\Components\TimePicker::make('selesai')
                            ->label('Waktu Selesai')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->required()
                            ->default(now()->addHours(2)),
                    ])->columns(2),

                Forms\Components\Section::make('Uraian Pekerjaan')
                    ->schema([
                        Forms\Components\RichEditor::make('pekerjaan')
                            ->label('Uraian Pekerjaan / Output')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'redo',
                                'undo',
                            ])
                            ->helperText('Gunakan bullets, numbering (1, 2, ...), atau format teks tebal/miring.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Dokumentasi Foto')
                    ->description('Wajib melampirkan minimal 2 foto.')
                    ->schema([
                        Forms\Components\FileUpload::make('foto_1')
                            ->label('Foto 1')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->required(),
                        Forms\Components\FileUpload::make('foto_2')
                            ->label('Foto 2')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->required(),
                        Forms\Components\FileUpload::make('foto_3')
                            ->label('Foto 3 (Opsional)')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120),
                        Forms\Components\FileUpload::make('foto_4')
                            ->label('Foto 4 (Opsional)')
                            ->image()
                            ->directory('lembur_photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag'])),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
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
                    ->label('Word')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function (LaporanLembur $record) {
                        $file = self::processWordDocument($record);
                        if ($file) {
                            return response()->download($file['path'], $file['name'])->deleteFileAfterSend();
                        }
                    }),
                Tables\Actions\Action::make('activities')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn ($record) => LaporanLemburResource::getUrl('activities', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('downloadBulk')
                        ->label('Download Semua (ZIP)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $zipFileName = 'Laporan_Lembur_Bulk_' . now()->format('YmdHis') . '.zip';
                            $zipPath = storage_path('app/' . $zipFileName);
                            $zip = new \ZipArchive();

                            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Gagal membuat file ZIP')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $tempFiles = [];
                            foreach ($records as $record) {
                                $file = self::processWordDocument($record, true);
                                if ($file) {
                                    $zip->addFile($file['path'], $file['name']);
                                    $tempFiles[] = $file['path'];
                                }
                            }
                            $zip->close();

                            // Clean up temp files
                            foreach ($tempFiles as $tempPath) {
                                if (file_exists($tempPath)) {
                                    unlink($tempPath);
                                }
                            }

                            return response()->download($zipPath)->deleteFileAfterSend();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function processWordDocument(LaporanLembur $record, $isBulk = false): ?array
    {
        $settings = app(\App\Settings\SystemSettings::class);
        $templatePath = $settings->laporan_lembur_template_path;
        $fullTemplatePath = $templatePath ? storage_path('app/public/' . $templatePath) : null;

        // Auto fallback to standard built-in template if missing, invalid, or wrong template
        $useDefault = false;
        if (!$fullTemplatePath || !file_exists($fullTemplatePath) || str_contains($templatePath, '01KZSSYYMF4VEDS91B1AJQX71D')) {
            $useDefault = true;
        } else {
            // Check if uploaded file contains lembur placeholder
            $zip = new \ZipArchive();
            if ($zip->open($fullTemplatePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if (!str_contains($xml, 'pekerjaan')) {
                    $useDefault = true;
                }
            }
        }

        if ($useDefault) {
            $defaultTemplate = resource_path('templates/template_daftar_hadir_lembur.docx');
            if (file_exists($defaultTemplate)) {
                $fullTemplatePath = $defaultTemplate;
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Template Laporan Lembur belum tersedia')
                    ->danger()
                    ->send();
                return null;
            }
        }

        $template = new \PhpOffice\PhpWord\TemplateProcessor($fullTemplatePath);

        // Format waktu: 'Jumat / 6 Maret 2026' (sesuai format dinas BPS)
        $waktuFormat = $record->waktu ? \Carbon\Carbon::parse($record->waktu)->locale('id')->translatedFormat('l / j F Y') : '-';
        $mulaiFormat = $record->mulai ? \Carbon\Carbon::parse($record->mulai)->format('H.i') . ' WIB' : '-';
        $selesaiFormat = $record->selesai ? \Carbon\Carbon::parse($record->selesai)->format('H.i') . ' WIB' : '-';

        $template->setValue('waktu', $waktuFormat);
        $template->setValue('unit_kerja', $settings->default_office_name ?? 'BPS Kabupaten Demak');

        // Format Nama Pegawai: title case nama depan, pertahankan gelar akademik di belakang koma
        $user = $record->user;
        $profile = $user?->profile;
        $rawName = $profile?->full_name ?? $user?->name ?? '-';
        $nameParts = explode(',', $rawName);
        $nameParts[0] = \Illuminate\Support\Str::title($nameParts[0]);
        $nama_pegawai = implode(',', $nameParts);
        $template->setValue('nama_pegawai', $nama_pegawai);

        // Variabel profil tambahan
        $template->setValue('nip_pegawai', $profile?->nip ?? '-');
        $template->setValue('jabatan', $profile?->jabatan ?? '-');
        $template->setValue('pangkat_golongan', $profile?->pangkat_golongan ?? '-');

        // Waktu mulai & selesai
        $template->setValue('mulai', $mulaiFormat);
        $template->setValue('selesai', $selesaiFormat);

        // Pejabat Penandatangan (Kepala)
        $template->setValue('jabatan_kepala', $settings->cert_signer_title ?? 'Kepala BPS Kab. Demak');
        $template->setValue('nama_kepala', $settings->cert_signer_name ?? 'Khomarudin, S. ST');
        $template->setValue('nip_kepala', $settings->cert_signer_nip ?? '197512091999011001');

        // Convert rich text HTML to OpenXML preserving bold, italic, underline, nested lists and paragraphs
        $pekerjaanXml = \App\Services\HtmlToWordXmlConverter::convert($record->pekerjaan, 'Arial', 20);
        $template->setValue('pekerjaan', $pekerjaanXml);

        // Pictures with try-catch error handling & aspect ratio
        for ($i = 1; $i <= 4; $i++) {
            $fotoField = "foto_{$i}";
            $fotoRelPath = $record->{$fotoField};
            $fullPath = $fotoRelPath ? storage_path('app/public/' . $fotoRelPath) : null;

            if ($fullPath && file_exists($fullPath)) {
                try {
                    $template->setImageValue($fotoField, [
                        'path' => $fullPath,
                        'width' => 280,
                        'height' => 320,
                        'ratio' => true
                    ]);
                } catch (\Exception $e) {
                    $template->setValue($fotoField, '[Error format foto]');
                }
            } else {
                $template->setValue($fotoField, '');
            }
        }

        $waktuFile = \Carbon\Carbon::parse($record->waktu)->format('Y_m_d');
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $user?->name ?? 'Pegawai');
        $namaFile = "Laporan_Lembur_{$safeName}_{$waktuFile}.docx";
        $prefix = $isBulk ? 'temp_bulk_lembur_' : 'temp_lembur_';
        $tempPath = storage_path("app/{$prefix}" . $namaFile);
        $template->saveAs($tempPath);

        return ['path' => $tempPath, 'name' => $namaFile];
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
            'activities' => Pages\ListLaporanLemburActivities::route('/{record}/activities'),
        ];
    }
}
