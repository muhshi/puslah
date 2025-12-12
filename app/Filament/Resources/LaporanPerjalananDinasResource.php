<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanPerjalananDinasResource\Pages;
use App\Models\LaporanPerjalananDinas;
use App\Models\SuratTugas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class LaporanPerjalananDinasResource extends Resource
{
    protected static ?string $model = LaporanPerjalananDinas::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Laporan Dinas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pilih Survey & Surat Tugas')->schema([
                    Forms\Components\Select::make('survey_id_temp')
                        ->label('Survey')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(function () {
                            $isSuperAdmin = Auth::user()->roles[0]->name === 'super_admin';

                            if ($isSuperAdmin) {
                                return \App\Models\Survey::pluck('name', 'id');
                            }

                            return \App\Models\Survey::whereHas('suratTugas', function ($q) {
                                $q->where('user_id', Auth::id());
                            })->pluck('name', 'id');
                        })
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $isSuperAdmin = Auth::user()->roles[0]->name === 'super_admin';

                                if ($isSuperAdmin) {
                                    // Super admin: don't auto-select, just reset
                                    $set('surat_tugas_id', null);
                                    $set('nomor_surat_tugas', '');
                                    $set('tujuan', '');
                                } else {
                                    // Regular user: auto-select their own surat tugas
                                    $st = SuratTugas::where('survey_id', $state)
                                        ->where('user_id', Auth::id())
                                        ->first();

                                    if ($st) {
                                        $set('surat_tugas_id', $st->id);
                                        $set('nomor_surat_tugas', $st->nomor_surat);
                                        $set('tujuan', $st->keperluan);
                                    }
                                }
                            }
                        })
                        ->helperText('Pilih survey untuk auto-fill data'),

                    Forms\Components\Select::make('surat_tugas_id')
                        ->label('Surat Tugas')
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $st = SuratTugas::find($state);
                                if ($st) {
                                    $set('nomor_surat_tugas', $st->nomor_surat);
                                    $set('tujuan', $st->keperluan);
                                }
                            }
                        })
                        ->options(function (Forms\Get $get) {
                            $isSuperAdmin = Auth::user()->roles[0]->name === 'super_admin';
                            $surveyId = $get('survey_id_temp');

                            $query = SuratTugas::with('user', 'survey');

                            if (!$isSuperAdmin) {
                                // Regular users: only their own surat tugas
                                $query->where('user_id', Auth::id());
                            }

                            if ($surveyId) {
                                // Filter by selected survey if any
                                $query->where('survey_id', $surveyId);
                            }

                            return $query->get()->mapWithKeys(function ($st) {
                                $survey = $st->survey ? " [{$st->survey->name}]" : '';
                                $user = $st->user ? " - {$st->user->name}" : '';
                                return [$st->id => "{$st->nomor_surat}{$survey}{$user}"];
                            });
                        })
                        ->required(),
                ])->columns(2),

                Forms\Components\Section::make('Data Laporan')->schema([
                    Forms\Components\TextInput::make('nomor_surat_tugas')
                        ->label('Nomor Surat Tugas')
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    Forms\Components\TextInput::make('tujuan')
                        ->label('Tujuan/Keperluan')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('tanggal_kunjungan')
                        ->label('Tanggal Kunjungan')
                        ->required(),

                    Forms\Components\RichEditor::make('uraian_kegiatan')
                        ->label('Uraian Kegiatan')
                        ->helperText('Gunakan bullets, numbering, atau format teks')
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
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nama_pejabat')
                        ->label('Nama Pejabat yang Dikunjungi')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('desa_pejabat')
                        ->label('Desa/Lokasi Pejabat')
                        ->maxLength(255),
                ])->columns(2),

                Forms\Components\Section::make('Dokumentasi Foto')->schema([
                    Forms\Components\Repeater::make('fotos')
                        ->relationship('fotos')
                        ->schema([
                            Forms\Components\FileUpload::make('file_path')
                                ->label('Foto')
                                ->image()
                                ->directory('laporan-foto')
                                ->maxSize(5120)
                                ->required(),
                            Forms\Components\Textarea::make('keterangan')
                                ->label('Keterangan Foto')
                                ->rows(2),
                        ])
                        ->reorderable('urutan')
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['keterangan'] ?? 'Foto')
                        ->defaultItems(0)
                        ->addActionLabel('Tambah Foto')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $isSuperAdmin = Auth::user()->roles[0]->name === 'super_admin';

                if (!$isSuperAdmin) {
                    $query->whereHas('suratTugas', function ($q) {
                        $q->where('user_id', Auth::id());
                    });
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('nomor_surat_tugas')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('suratTugas.survey.name')
                    ->label('Survey')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tujuan')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal_kunjungan')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fotos_count')
                    ->counts('fotos')
                    ->label('Foto')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('downloadWord')
                    ->label('Word')
                    ->icon('heroicon-o-document-text')
                    ->action(function (LaporanPerjalananDinas $record) {
                        return self::generateWordDocument($record);
                    }),

                Tables\Actions\Action::make('downloadPernyataan')
                    ->label('Surat Pernyataan')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->visible(function (LaporanPerjalananDinas $record) {
                        // Only visible if user is Pegawai BPS
                        $user = $record->suratTugas->user;
                        return $user->roles->pluck('name')->contains('Pegawai BPS');
                    })
                    ->action(function (LaporanPerjalananDinas $record) {
                        return self::generateSuratPernyataan($record);
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function generateWordDocument(LaporanPerjalananDinas $record)
    {
        $settings = app(\App\Settings\SystemSettings::class);
        $templatePath = $settings->laporan_dinas_template_path;

        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
            \Filament\Notifications\Notification::make()
                ->title('Template Laporan Dinas belum diupload di Pengaturan Sistem')
                ->danger()
                ->send();
            return;
        }

        $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));

        $st = $record->suratTugas;
        $template->setValue('nama_pegawai', \Illuminate\Support\Str::title($st->user->name));
        $template->setValue('nomor_surat_tugas', $record->nomor_surat_tugas);
        $template->setValue('tujuan', $record->tujuan);
        $template->setValue('tanggal_kunjungan', $record->tanggal_kunjungan->translatedFormat('d F Y'));

        // Strip HTML tags from rich text editor
        $template->setValue('uraian_kegiatan', strip_tags($record->uraian_kegiatan));

        $template->setValue('nama_pejabat', $record->nama_pejabat ?? '-');
        $template->setValue('desa_pejabat', $record->desa_pejabat ?? '-');

        // Photos (max 10)
        $fotos = $record->fotos()->orderBy('urutan')->take(10)->get();
        for ($i = 1; $i <= 10; $i++) {
            $foto = $fotos->get($i - 1);
            if ($foto && Storage::exists('public/' . $foto->file_path)) {
                $template->setImageValue("foto_{$i}", [
                    'path' => storage_path('app/public/' . $foto->file_path),
                    'width' => 600,
                    'ratio' => true
                ]);
                $template->setValue("keterangan_foto_{$i}", $foto->keterangan ?? '');
            } else {
                $template->setValue("foto_{$i}", '');
                $template->setValue("keterangan_foto_{$i}", '');
            }
        }

        // Save temp
        $safeFilename = str_replace(['/', '\\'], '_', $record->nomor_surat_tugas);
        $fileName = "Laporan_Dinas_{$safeFilename}.docx";
        $tempPath = storage_path('app/temp_laporan_' . $fileName);
        $template->saveAs($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend();
    }

    protected static function generateSuratPernyataan(LaporanPerjalananDinas $record)
    {
        $settings = app(\App\Settings\SystemSettings::class);
        $templatePath = $settings->surat_pernyataan_template_path;

        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
            \Filament\Notifications\Notification::make()
                ->title('Template Surat Pernyataan belum diupload di Pengaturan Sistem')
                ->danger()
                ->send();
            return;
        }

        $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));

        $user = $record->suratTugas->user;
        $profile = $user->profile;

        // Map data
        $template->setValue('nama_pegawai', \Illuminate\Support\Str::title($user->name));
        $template->setValue('nip_pegawai', $profile->nip ?? '-');
        $template->setValue('pangkat_golongan', $profile->pangkat_golongan ?? '-');
        $template->setValue('jabatan', $profile->jabatan ?? '-');
        $template->setValue('unit_kerja', $settings->default_office_name ?? 'BPS Kabupaten Demak');
        $template->setValue('tanggal_pernyataan', $record->tanggal_kunjungan->translatedFormat('d F Y'));

        // Save temp
        $safeFilename = str_replace(['/', '\\'], '_', $record->nomor_surat_tugas);
        $fileName = "Surat_Pernyataan_{$safeFilename}.docx";
        $tempPath = storage_path('app/temp_pernyataan_' . $fileName);
        $template->saveAs($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend();
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
            'index' => Pages\ListLaporanPerjalananDinas::route('/'),
            'create' => Pages\CreateLaporanPerjalananDinas::route('/create'),
            'edit' => Pages\EditLaporanPerjalananDinas::route('/{record}/edit'),
        ];
    }
}
