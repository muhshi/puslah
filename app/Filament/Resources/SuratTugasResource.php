<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratTugasResource\Pages;
use App\Filament\Resources\SuratTugasResource\RelationManagers;
use App\Models\SuratTugas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Settings\SystemSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Group;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\User;
use App\Models\UserProfile;

class SuratTugasResource extends Resource
{
    protected static ?string $model = SuratTugas::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Surat Tugas';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Tugas')->schema([
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Select::make('survey_id')
                            ->label('Survey (Opsional)')
                            ->relationship('survey', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Reset user_id when survey changes
                                $set('user_id', null);
                                // Auto-fill keperluan if survey selected
                                if ($state) {
                                    $survey = \App\Models\Survey::find($state);
                                    if ($survey) {
                                        $set('keperluan', "Pendataan {$survey->name}");
                                    }
                                }
                            }),

                        Forms\Components\Select::make('user_id')
                            ->label('Pegawai yang Ditugaskan')
                            ->options(function (Get $get) {
                                $surveyId = $get('survey_id');

                                // If survey selected, filter by survey participants only
                                if ($surveyId) {
                                    return \App\Models\SurveyUser::where('survey_id', $surveyId)
                                        ->with('user.profile')
                                        ->get()
                                        ->mapWithKeys(function ($su) {
                                            $jabatan = $su->user->profile->jabatan ?? '-';
                                            return [$su->user_id => "{$su->user->name} ({$jabatan})"];
                                        });
                                }

                                // If no survey, show all users
                                return User::with('profile')
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                    $jabatan = $user->profile->jabatan ?? '-';
                                    return [$user->id => "{$user->name} ({$jabatan})"];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $profile = UserProfile::where('user_id', $state)->first();
                                    if ($profile && $profile->jabatan) {
                                        $set('jabatan', $profile->jabatan);
                                    }
                                }
                            })
                            ->required(),

                        Forms\Components\TextInput::make('jabatan')
                            ->label('Jabatan (Saat Tugas)')
                            ->helperText('Otomatis diambil dari profil, bisa diedit jika perlu.')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('nomor_surat')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(function () {
                                // Calculate default number on load
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';

                                // Default uses current year since tanggal default is now()
                                $year = now()->year;
                                $max = SuratTugas::whereYear('tanggal', $year)->max('nomor_urut');
                                $nextUrut = $max ? $max + 1 : 1;
                                $urut = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);

                                $klasifikasi = 'KP.650';

                                return "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";
                            })
                            ->helperText('Otomatis: Prefix-Urut/Kantor/Klasifikasi/Tahun. Bisa diedit manual.'),

                        Section::make('Generator Nomor Surat')
                            ->description('Ubah komponen ini untuk menghasilkan nomor surat.')
                            ->schema([
                                Forms\Components\TextInput::make('kode_klasifikasi')
                                    ->label('Klasifikasi')
                                    ->default('KP.650')
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::updateNomorSurat($get, $set);
                                    }),
                                Forms\Components\TextInput::make('nomor_urut')
                                    ->label('No. Urut')
                                    ->numeric()
                                    ->default(function () {
                                        // Get max nomor_urut for current year (tanggal default is now)
                                        $max = SuratTugas::whereYear('tanggal', now()->year)->max('nomor_urut');
                                        return $max ? $max + 1 : 1;
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::updateNomorSurat($get, $set);
                                    }),
                            ])->columns(2),
                    ])->columns(1),

                    Forms\Components\Group::make()->schema([
                        Forms\Components\DatePicker::make('tanggal')
                            ->label('Tanggal Surat')
                            ->required()
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                // If year changes, re-fetch max sequence for that year
                                if ($state) {
                                    $year = \Carbon\Carbon::parse($state)->year;
                                    $max = SuratTugas::whereYear('tanggal', $year)->max('nomor_urut');
                                    $next = $max ? $max + 1 : 1;
                                    $set('nomor_urut', $next);
                                }
                                self::updateNomorSurat($get, $set);
                            }),
                        Forms\Components\DateTimePicker::make('waktu_mulai')
                            ->label('Mulai')
                            ->seconds(false)
                            ->default(now()->setTime(8, 0)),
                        Forms\Components\DateTimePicker::make('waktu_selesai')
                            ->label('Selesai')
                            ->seconds(false)
                            ->default(now()->setTime(16, 0)),
                    ])->columns(1),

                    Forms\Components\Textarea::make('keperluan')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('tempat_tugas')
                        ->label('Tempat Tugas')
                        ->placeholder('Contoh: Kecamatan Demak')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make('Pejabat Penandatangan (Snapshot)')
                    ->description('Data ini tersimpan di surat dan tidak akan berubah meski pengaturan sistem diganti.')
                    ->schema([
                        Forms\Components\TextInput::make('signer_city')
                            ->label('Kota Penetapan')
                            ->default(fn() => app(SystemSettings::class)->cert_city)
                            ->readOnly(),
                        Forms\Components\TextInput::make('signer_name')
                            ->label('Nama Pejabat')
                            ->default(fn() => app(SystemSettings::class)->cert_signer_name)
                            ->readOnly(),
                        Forms\Components\TextInput::make('signer_nip')
                            ->label('NIP')
                            ->default(fn() => app(SystemSettings::class)->cert_signer_nip)
                            ->readOnly(),
                        Forms\Components\TextInput::make('signer_title')
                            ->label('Jabatan Pejabat')
                            ->default(fn() => app(SystemSettings::class)->cert_signer_title)
                            ->readOnly()
                            ->columnSpanFull(),

                        // Hidden signature path
                        Forms\Components\Hidden::make('signer_signature_path')
                            ->default(fn() => app(SystemSettings::class)->cert_signer_signature_path),
                    ])
                    ->collapsed() // Collapsed by default so it doesn't clutter
                    ->columns(3),

            ]);

    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_surat')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('survey.name')
                    ->label('Nama Survei')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tanggal')
                    ->date()
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
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->visible(false)
                    ->color('info')
                    ->url(fn(SuratTugas $record) => route('surat-tugas.preview', $record->id))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (SuratTugas $record) {
                        return $record->status === 'pending' && auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']);
                    })
                    ->action(fn(SuratTugas $record) => $record->update(['status' => 'approved'])),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color(fn(SuratTugas $record) => $record->status === 'approved' ? 'danger' : 'gray')
                    ->action(function (SuratTugas $record) {
                        // 1. Ensure Hash exists
                        if (!$record->hash) {
                            $record->update(['hash' => \Illuminate\Support\Str::random(32)]);
                        }

                        // 2. Load Logo Base64
                        $logoBase64 = \Illuminate\Support\Facades\Cache::remember('logo_bps_static_base64', 86400, function () {
                            $logoPath = public_path('images/logo_bps.png');
                            if (file_exists($logoPath)) {
                                return 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
                            }
                            return null;
                        });

                        // 3. Generate QR (ONLY IF APPROVED)
                        $qrBase64 = null;
                        if ($record->status === 'approved') {
                            $verifyUrl = route('surat-tugas.verify', $record->hash);
                            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl);
                            $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
                        }

                        // 4. Prepare Data
                        $periode = self::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);

                        // 5. Generate PDF
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('surat-tugas.pdf_table_layout', [
                            'surat' => $record,
                            'logoBase64' => $logoBase64,
                            'qrBase64' => $qrBase64,
                            'periode' => $periode,
                            'is_preview' => false,
                        ])->setPaper('a4', 'portrait');

                        return response()->streamDownload(fn() => print ($pdf->output()), str_replace(['/', '\\'], '_', $record->nomor_surat) . '.pdf');
                    }),
                Tables\Actions\Action::make('word')
                    ->label('Word')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(true) // Hidden as per request
                    ->action(function (SuratTugas $record) {
                        $settings = app(SystemSettings::class);
                        $templatePath = $settings->surat_tugas_template_path; // Stored in storage/app/public/templates
            
                        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
                            \Filament\Notifications\Notification::make()
                                ->title('Template belum diupload di Pengaturan Sistem')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Initialize TemplateProcessor
                        $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));

                        // Map variables
                        $template->setValue('nomor_surat', $record->nomor_surat);
                        $template->setValue('nama_pegawai', \Illuminate\Support\Str::title($record->user->name));
                        // Assumption: UserProfile stores NIP in 'nip' field? No, user model usually. 
                        // Checking UserProfile again, it has 'jabatan' but not explicitly NIP. 
                        // Assuming NIP might be in User or UserProfile (Check required). 
                        // For now using '-' or placeholder if not found. Only Jabatan was added.
                        // Wait, user_profiles table schema: user_id, full_name, phone, employment_status, jabatan.
                        // I'll stick to what I have:
                        $template->setValue('nip_pegawai', '-'); // TODO: Add NIP to UserProfile if needed
                        $template->setValue('jabatan_pegawai', $record->user->profile->jabatan ?? '-');

                        $template->setValue('jabatan_tugas', $record->jabatan);
                        $template->setValue('keperluan', $record->keperluan);
                        $template->setValue('dasar_surat', $record->survey?->dasar_surat ?? '-');
                        $template->setValue('tempat_tugas', $record->tempat_tugas ?? '-');
                        $template->setValue('tanggal_surat', $record->tanggal->translatedFormat('d F Y'));

                        // Smart date range formatter
                        $periodeTugas = self::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);
                        $template->setValue('periode_tugas', $periodeTugas);

                        // Signer Snapshot
                        $template->setValue('nama_kepala', $record->signer_name);
                        $template->setValue('nip_kepala', $record->signer_nip);
                        $template->setValue('jabatan_kepala', $record->signer_title);
                        $template->setValue('kota_penetapan', $record->signer_city);

                        // Save to temp file
                        $safeFilename = str_replace(['/', '\\'], '_', $record->nomor_surat);
                        $fileName = "Surat_Tugas_{$safeFilename}.docx";
                        $tempPath = storage_path('app/temp_' . $fileName);
                        $template->saveAs($tempPath);
                        return response()->download($tempPath)->deleteFileAfterSend();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->fromTable()
                            ->withFilename('Surat_Tugas_' . date('Y-m-d'))
                            ->withColumns([
                                \pxlrbt\FilamentExcel\Columns\Column::make('nomor_surat'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('user.name')->heading('Pegawai'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('jabatan')->heading('Jabatan'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('survey.name')->heading('Survei'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('tanggal'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('keperluan'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('tempat_tugas'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('waktu_mulai'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('waktu_selesai'),
                            ]),
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approveBulk')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'kepala', 'kasubag']))
                        ->action(fn(\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['status' => 'approved'])),
                    \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make(),
                    Tables\Actions\BulkAction::make('downloadBulk')
                        ->label('Download Semua (ZIP)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $settings = app(SystemSettings::class);
                            $templatePath = $settings->surat_tugas_template_path;

                            if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Template belum diupload di Pengaturan Sistem')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Create ZIP
                            $zipFileName = 'Surat_Tugas_Bulk_' . now()->format('YmdHis') . '.zip';
                            $zipPath = storage_path('app/' . $zipFileName);
                            $zip = new \ZipArchive();

                            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Gagal membuat file ZIP')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            foreach ($records as $record) {
                                $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));

                                // Map all variables (same as single download)
                                $template->setValue('nomor_surat', $record->nomor_surat);
                                $template->setValue('nama_pegawai', \Illuminate\Support\Str::title($record->user->name));
                                $template->setValue('nip_pegawai', '-');
                                $template->setValue('jabatan_pegawai', $record->user->profile->jabatan ?? '-');
                                $template->setValue('jabatan_tugas', $record->jabatan);
                                $template->setValue('keperluan', $record->keperluan);
                                $template->setValue('dasar_surat', $record->survey?->dasar_surat ?? '-');
                                $template->setValue('tempat_tugas', $record->tempat_tugas ?? '-');
                                $template->setValue('tanggal_surat', $record->tanggal->translatedFormat('d F Y'));

                                $periodeTugas = self::formatPeriodeTugas($record->waktu_mulai, $record->waktu_selesai);
                                $template->setValue('periode_tugas', $periodeTugas);

                                $template->setValue('nama_kepala', $record->signer_name);
                                $template->setValue('nip_kepala', $record->signer_nip);
                                $template->setValue('jabatan_kepala', $record->signer_title);
                                $template->setValue('kota_penetapan', $record->signer_city);

                                // Save to temp
                                $safeFilename = str_replace(['/', '\\'], '_', $record->nomor_surat);
                                $fileName = "Surat_Tugas_{$safeFilename}.docx";
                                $tempPath = storage_path('app/temp_bulk_' . $fileName);
                                $template->saveAs($tempPath);

                                // Add to ZIP
                                $zip->addFile($tempPath, $fileName);
                            }

                            $zip->close();

                            // Clean up temp files
                            foreach ($records as $record) {
                                $safeFilename = str_replace(['/', '\\'], '_', $record->nomor_surat);
                                $tempPath = storage_path('app/temp_bulk_Surat_Tugas_' . $safeFilename . '.docx');
                                if (file_exists($tempPath)) {
                                    unlink($tempPath);
                                }
                            }

                            return response()->download($zipPath)->deleteFileAfterSend();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user && !$user->hasRole(['super_admin', 'Admin Pegawai', 'Kepala'])) {
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
            'index' => Pages\ListSuratTugas::route('/'),
            'create' => Pages\CreateSuratTugas::route('/create'),
            'create-bulk' => Pages\CreateBulkSuratTugas::route('/create-bulk'),
            'edit' => Pages\EditSuratTugas::route('/{record}/edit'),
        ];
    }

    protected static function updateNomorSurat(Get $get, Set $set): void
    {
        $settings = app(SystemSettings::class);
        $prefix = $settings->surat_prefix ?? 'B';
        $office = $settings->office_code ?? '33210';

        $urut = str_pad($get('nomor_urut') ?? 0, 4, '0', STR_PAD_LEFT);
        $klasifikasi = $get('kode_klasifikasi') ?? 'KP.650';

        // Use tanggal year if available, else current year
        $tanggal = $get('tanggal');
        $year = $tanggal ? \Carbon\Carbon::parse($tanggal)->year : now()->year;

        $nomor = "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";
        $set('nomor_surat', $nomor);
    }

    public static function formatPeriodeTugas($mulai, $selesai): string
    {
        if (!$mulai || !$selesai) {
            return '-';
        }

        $startDate = \Carbon\Carbon::parse($mulai);
        $endDate = \Carbon\Carbon::parse($selesai);

        // Case 1: Same date
        if ($startDate->isSameDay($endDate)) {
            return $startDate->translatedFormat('d F Y');
        }

        // Case 2: Same month and year
        if ($startDate->month === $endDate->month && $startDate->year === $endDate->year) {
            return $startDate->translatedFormat('d') . ' - ' . $endDate->translatedFormat('d F Y');
        }

        // Case 3: Same year, different month
        if ($startDate->year === $endDate->year) {
            return $startDate->translatedFormat('d F') . ' - ' . $endDate->translatedFormat('d F Y');
        }

        // Case 4: Different year
        return $startDate->translatedFormat('d F Y') . ' - ' . $endDate->translatedFormat('d F Y');
    }
}
