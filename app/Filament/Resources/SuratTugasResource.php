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
                                // Auto-fill keperluan and dates if survey selected
                                if ($state) {
                                    $survey = \App\Models\Survey::find($state);
                                    if ($survey) {
                                        $set('keperluan', "{$survey->name}");
                                        // Auto-fill waktu_mulai/selesai from survey dates
                                        if ($survey->start_date) {
                                            $set('waktu_mulai', \Carbon\Carbon::parse($survey->start_date)->format('Y-m-d'));
                                        }
                                        if ($survey->end_date) {
                                            $set('waktu_selesai', \Carbon\Carbon::parse($survey->end_date)->format('Y-m-d'));
                                        }
                                    }

                                    // Checker for empty users
                                    $query = \App\Models\SurveyUser::where('survey_id', $state);
                                    if (!$survey->is_multiple) {
                                        $query->whereDoesntHave('user.suratTugas', function ($q) use ($state) {
                                            $q->where('survey_id', $state);
                                        });
                                    }
                                    $hasParticipants = $query->exists();

                                    if (!$hasParticipants) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Semua petugas pada survey ini sudah memiliki Surat Tugas.')
                                            ->warning()
                                            ->send();
                                    }
                                }
                            }),

                        Forms\Components\Select::make('user_id')
                            ->label('Pegawai yang Ditugaskan')
                            ->relationship('user', 'name')
                            ->options(function (Get $get) {
                                $surveyId = $get('survey_id');

                                // If survey selected, filter by survey participants only
                                if ($surveyId) {
                                    $survey = \App\Models\Survey::find($surveyId);
                                    $query = \App\Models\SurveyUser::where('survey_id', $surveyId);
                                    
                                    if ($survey && !$survey->is_multiple) {
                                        $query->whereDoesntHave('user.suratTugas', function ($q) use ($surveyId) {
                                            $q->where('survey_id', $surveyId);
                                        });
                                    }

                                    return $query->with('user.profile')
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
                                $max = SuratTugas::getNextNomorUrut($year) - 1;
                                $nextUrut = $max + 1;
                                $urut = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);

                                $klasifikasi = 'KP.650';

                                return "{$prefix}-{$urut}/{$office}/{$klasifikasi}/{$year}";
                            })
                            ->readOnly()
                            ->helperText('Otomatis dibuat oleh sistem.'),

                        Forms\Components\Hidden::make('kode_klasifikasi')
                            ->default('KP.650'),
                        Forms\Components\Hidden::make('nomor_urut')
                            ->default(function () {
                                return SuratTugas::getNextNomorUrut(now()->year);
                            }),
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
                                    $next = SuratTugas::getNextNomorUrut($year);
                                    $set('nomor_urut', $next);

                                    // For SPPD
                                    $nextSppd = SuratTugas::getNextNomorUrutSppd($year);
                                    $set('nomor_urut_sppd', $nextSppd);
                                }
                                self::updateNomorSurat($get, $set);
                                self::updateNomorSppd($get, $set);
                            }),
                        Forms\Components\DatePicker::make('waktu_mulai')
                            ->label('Mulai')
                            ->default(now()),
                        Forms\Components\DatePicker::make('waktu_selesai')
                            ->label('Selesai')
                            ->default(now()),
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

                Section::make('Informasi SPPD')->schema([
                    Forms\Components\Toggle::make('is_sppd')
                        ->label('Memerlukan SPPD?')
                        ->default(false)
                        ->dehydrated(false) // Don't save this field automatically
                        ->live(),

                    Forms\Components\Group::make()->schema([
                        Forms\Components\TextInput::make('nomor_sppd')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(function () {
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';
                                $year = now()->year;
                                $nextUrut = SuratTugas::getNextNomorUrutSppd($year);
                                $urut = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);
                                $klasifikasi = 'KP.650';

                                return "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $state) {
                                if (preg_match('/(?:B-)?0*(\d+)\//', $state, $matches)) {
                                    $set('nomor_urut_sppd', (int)$matches[1]);
                                }
                            })
                            ->helperText('Otomatis di-generate saat disimpan.'),

                        Forms\Components\Hidden::make('kode_klasifikasi_sppd')
                            ->default('KP.650'),
                            
                        Forms\Components\TextInput::make('nomor_urut_sppd')
                            ->label('No. Urut SPPD')
                            ->numeric()
                            ->default(function () {
                                return SuratTugas::getNextNomorUrutSppd(now()->year);
                            })
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateNomorSppd($get, $set);
                            }),

                        Forms\Components\Select::make('tingkat_perjalanan_dinas')
                            ->label('Tingkat Perjalanan Dinas')
                            ->options([
                                'A' => 'Tingkat A',
                                'B' => 'Tingkat B',
                                'C' => 'Tingkat C',
                            ])
                            ->default('C'),

                        Forms\Components\TextInput::make('alat_angkutan')
                            ->label('Alat Angkutan')
                            ->default('Kendaraan Pribadi')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('mak')
                            ->label('Pembebanan Anggaran (MAK)')
                            ->default('054.01.GG.2902.006.005.A.524113')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('maksud_perjalanan')
                            ->label('Maksud Perjalanan Dinas')
                            ->default(fn (Get $get) => $get('keperluan'))
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('tempat_berangkat')
                            ->label('Tempat Berangkat')
                            ->default('Demak')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tempat_tujuan')
                            ->label('Tempat Tujuan')
                            ->placeholder('Kosongkan untuk mengikuti Tempat Tugas')
                            ->maxLength(255),
                    ])->columns(2)->visible(fn (Get $get) => $get('is_sppd')),
                ]),

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
                        Forms\Components\Textarea::make('signer_title')
                            ->label('Jabatan Pejabat')
                            ->rows(3)
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
                    ->sortable()
                    ->wrap() // Allow wrapping for long numbers
                    ->width('18%'), // Explicit width suggestion
                Tables\Columns\TextColumn::make('survey.name')
                    ->label('Nama Survei')
                    ->searchable()
                    ->sortable()
                    ->limit(40) // Limit characters
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        return $column->getState();
                    })
                    ->wrap(), // Wrap if still long within limit
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pegawai')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        return $column->getState();
                    }),
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
                Tables\Filters\SelectFilter::make('survey_id')
                    ->label('Filter berdasarkan Survey')
                    ->relationship('survey', 'name')
                    ->searchable()
                    ->preload(),
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

                        // 6. Set Encryption if Master Password is set
                        $settings = app(SystemSettings::class);
                        if (!empty($settings->pdf_master_password)) {
                            // User password null (open freely), Owner password set, Permissions: print only
                            $pdf->setEncryption('', $settings->pdf_master_password, ['print']);
                        }

                        $surveyName = $record->survey ? str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->survey->name) : 'NoSurvey';
                        $userName = str_replace(['/', '\\', ' '], ['_', '_', '_'], $record->user->name);
                        $nomorSurat = str_replace(['/', '\\'], '_', $record->nomor_surat);
                        $fileName = "{$nomorSurat}-{$surveyName}-{$userName}.pdf";
                        return response()->streamDownload(fn() => print ($pdf->output()), $fileName);
                    }),

                Tables\Actions\Action::make('generate_sppd')
                    ->label('Buat SPPD')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn(SuratTugas $record) => !$record->sppd()->exists() && auth()->user()->hasAnyRole(['super_admin', 'Kasubag', 'Kepala', 'Operator', 'Ketua Tim', 'Pegawai']))
                    ->form([
                        Forms\Components\TextInput::make('nomor_sppd')
                            ->label('Nomor SPPD')
                            ->required()
                            ->maxLength(255)
                            ->unique('sppds', 'nomor_sppd')
                            ->default(function (SuratTugas $record) {
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';
                                $year = \Carbon\Carbon::parse($record->tanggal)->year;
                                $nextUrut = SuratTugas::getNextNomorUrutSppd($year);
                                $urut = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);
                                $klasifikasi = 'KP.650';
                                return "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $state) {
                                if (preg_match('/(?:B-)?0*(\d+)\//', $state, $matches)) {
                                    $set('nomor_urut_sppd', (int)$matches[1]);
                                }
                            }),
                        Forms\Components\TextInput::make('nomor_urut_sppd')
                            ->label('No. Urut SPPD')
                            ->numeric()
                            ->required()
                            ->default(function (SuratTugas $record) {
                                return SuratTugas::getNextNomorUrutSppd(\Carbon\Carbon::parse($record->tanggal)->year);
                            })
                            ->live()
                            ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $state) {
                                if (blank($state)) return;
                                $settings = app(SystemSettings::class);
                                $prefix = $settings->surat_prefix ?? 'B';
                                $office = $settings->office_code ?? '33210';
                                $urut = str_pad($state, 4, '0', STR_PAD_LEFT);
                                $klasifikasi = $get('kode_klasifikasi_sppd') ?? 'KP.650';
                                $year = $get('tahun_sppd') ?? now()->year;
                                $set('nomor_sppd', "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}");
                            }),
                        Forms\Components\Hidden::make('kode_klasifikasi_sppd')->default('KP.650'),
                        Forms\Components\Hidden::make('tahun_sppd')->default(fn(SuratTugas $record) => \Carbon\Carbon::parse($record->tanggal)->year),
                        Forms\Components\Select::make('tingkat_perjalanan_dinas')
                            ->label('Tingkat Perjalanan Dinas')
                            ->options([
                                'A' => 'Tingkat A',
                                'B' => 'Tingkat B',
                                'C' => 'Tingkat C',
                            ])
                            ->default('C')
                            ->required(),
                        Forms\Components\TextInput::make('alat_angkutan')
                            ->label('Alat Angkutan')
                            ->default('Kendaraan Pribadi')
                            ->maxLength(255)
                            ->required(),
                        Forms\Components\TextInput::make('mak')
                            ->label('Pembebanan Anggaran (MAK)')
                            ->default('054.01.GG.2902.006.005.A.524113')
                            ->maxLength(255)
                            ->required(),
                        Forms\Components\Textarea::make('maksud_perjalanan')
                            ->label('Maksud Perjalanan Dinas')
                            ->default(fn(SuratTugas $record) => "Transport lokal dalam rangka {$record->keperluan}")
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('tempat_berangkat')
                            ->label('Tempat Berangkat')
                            ->default('Demak')
                            ->maxLength(255)
                            ->required(),
                        Forms\Components\TextInput::make('tempat_tujuan')
                            ->label('Tempat Tujuan')
                            ->default(fn(SuratTugas $record) => $record->tempat_tugas)
                            ->maxLength(255)
                            ->required(),
                    ])
                    ->action(function (SuratTugas $record, array $data) {
                        $settings = app(SystemSettings::class);

                        $record->sppd()->create([
                            'nomor_sppd' => $data['nomor_sppd'],
                            'nomor_urut_sppd' => $data['nomor_urut_sppd'],
                            'kode_klasifikasi_sppd' => $data['kode_klasifikasi_sppd'] ?? 'KP.650',
                            'tingkat_perjalanan_dinas' => $data['tingkat_perjalanan_dinas'],
                            'alat_angkutan' => $data['alat_angkutan'],
                            'mak' => $data['mak'],
                            'maksud_perjalanan' => $data['maksud_perjalanan'],
                            'tempat_berangkat' => $data['tempat_berangkat'],
                            'tempat_tujuan' => $data['tempat_tujuan'],
                            'ppk_name' => $settings->ppk_name,
                            'ppk_nip' => $settings->ppk_nip,
                            'ppk_title' => $settings->ppk_title,
                        ]);
                        \Filament\Notifications\Notification::make()->title('SPPD Berhasil Dibuat')->success()->send();
                    }),
                Tables\Actions\Action::make('word_sppd')
                    ->label('Cetak SPPD')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn(SuratTugas $record) => $record->sppd()->exists())
                    ->action(function (SuratTugas $record) {
                        $settings = app(SystemSettings::class);
                        $templatePath = $settings->sppd_template_path;
            
                        if (!$templatePath || !file_exists(storage_path('app/public/' . $templatePath))) {
                            \Filament\Notifications\Notification::make()
                                ->title('Template SPPD belum diupload di Pengaturan Sistem')
                                ->danger()
                                ->send();
                            return;
                        }

                        $template = new TemplateProcessor(storage_path('app/public/' . $templatePath));
                        $sppd = $record->sppd;

                        $template->setValue('nomor_sppd', $sppd->nomor_sppd);
                        $ppkName = $sppd->ppk_name ?? $settings->ppk_name;
                        $ppkNip = $sppd->ppk_nip ?? $settings->ppk_nip;
                        
                        $template->setValue('nama_ppk', $ppkName);
                        $template->setValue('nip_ppk', $ppkNip);
                        
                        $template->setValue('nama_kepala', $record->signer_name ?? $settings->cert_signer_name);
                        $template->setValue('nip_kepala', $record->signer_nip ?? $settings->cert_signer_nip);
                        $template->setValue('nomor_surat', $record->nomor_surat);
                        
                        $template->setValue('nama_pegawai', $record->user->profile->full_name ?? $record->user->name);
                        $template->setValue('nip_pegawai', $record->user->profile->nip ?? '-');
                        $template->setValue('pangkat_golongan', $record->user->profile->pangkat_golongan ?? '-');
                        $template->setValue('jabatan_pegawai', $record->user->profile->jabatan ?? '-');
                        $template->setValue('jabatan', $record->user->profile->jabatan ?? '-');
                        $template->setValue('unit_kerja', 'Badan Pusat Statistik Kabupaten Demak');
                        
                        $template->setValue('tingkat_perjalanan', $sppd->tingkat_perjalanan_dinas ?? '-');
                        $template->setValue('maksud_perjalanan', $sppd->maksud_perjalanan ?? "Transport lokal dalam rangka {$record->keperluan}");
                        $template->setValue('alat_angkutan', $sppd->alat_angkutan ?? '-');
                        $template->setValue('tempat_berangkat', $sppd->tempat_berangkat ?? 'Demak'); 
                        $template->setValue('tempat_tujuan', $sppd->tempat_tujuan ?? $record->tempat_tugas ?? '-');
                        
                        $start = \Carbon\Carbon::parse($record->waktu_mulai);
                        $end = \Carbon\Carbon::parse($record->waktu_selesai);
                        $lama = $start->diffInDays($end) + 1; 
                        $terbilang = [1=>'satu', 2=>'dua', 3=>'tiga', 4=>'empat', 5=>'lima', 6=>'enam', 7=>'tujuh', 8=>'delapan', 9=>'sembilan', 10=>'sepuluh', 11=>'sebelas', 12=>'dua belas', 13=>'tiga belas', 14=>'empat belas', 15=>'lima belas', 30=>'tiga puluh', 31=>'tiga puluh satu'];
                        $lamaText = $terbilang[$lama] ?? $lama;
                        
                        $template->setValue('lama_perjalanan', $lama . ' (' . $lamaText . ') hari');
                        $template->setValue('tanggal_berangkat', $start->translatedFormat('d F Y'));
                        $template->setValue('tanggal_kembali', $end->translatedFormat('d F Y'));
                        $template->setValue('mak', $sppd->mak ?? '-');
                        $template->setValue('nomor_surat_tugas', $record->nomor_surat);
                        $template->setValue('tanggal_surat', \Carbon\Carbon::parse($record->tanggal)->translatedFormat('d F Y'));
                        $template->setValue('tanggal_pernyataan', \Carbon\Carbon::parse($record->tanggal)->translatedFormat('d F Y'));

                        $safeFilename = str_replace(['/', '\\'], '_', $sppd->nomor_sppd);
                        $fileName = "SPPD_{$safeFilename}.docx";
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
                        ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag']))
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = $records->where('status', '!=', 'approved')->count();
                            $records->each->update(['status' => 'approved']);

                            \Filament\Notifications\Notification::make()
                                ->title('Berhasil!')
                                ->body("{$count} surat tugas telah di-approve.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('generateSppdBulk')
                        ->label('Buat SPPD')
                        ->icon('heroicon-o-document-plus')
                        ->color('primary')
                        ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'Kepala', 'Kasubag', 'Operator', 'Ketua Tim', 'Pegawai']))
                        ->form([
                            Forms\Components\TextInput::make('nomor_urut_sppd_mulai')
                                ->label('Nomor Urut Mulai')
                                ->numeric()
                                ->required()
                                ->default(function () {
                                    return SuratTugas::getNextNomorUrutSppd(now()->year);
                                })
                                ->helperText('Nomor urut akan di-increment otomatis untuk setiap SPPD dari angka ini.'),
                            Forms\Components\TextInput::make('format_nomor_sppd')
                                ->label('Format Nomor SPPD')
                                ->required()
                                ->default(function () {
                                    $settings = app(SystemSettings::class);
                                    $prefix = $settings->surat_prefix ?? 'B';
                                    $office = $settings->office_code ?? '33210';
                                    $klasifikasi = 'KP.650';
                                    $year = now()->year;
                                    return "{$prefix}-{urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
                                })
                                ->helperText('Gunakan {urut} di mana nomor urut akan disisipkan (contoh: B-{urut}/33210/SE2026/KP.650/2026)'),
                            Forms\Components\Select::make('tingkat_perjalanan_dinas')
                                ->label('Tingkat Perjalanan Dinas')
                                ->options([
                                    'A' => 'Tingkat A',
                                    'B' => 'Tingkat B',
                                    'C' => 'Tingkat C',
                                ])
                                ->default('C')
                                ->required(),
                            Forms\Components\TextInput::make('alat_angkutan')
                                ->label('Alat Angkutan')
                                ->default('Kendaraan Pribadi')
                                ->maxLength(255)
                                ->required(),
                            Forms\Components\TextInput::make('mak')
                                ->label('Pembebanan Anggaran (MAK)')
                                ->default('054.01.GG.2902.006.005.A.524113')
                                ->maxLength(255)
                                ->required(),
                            Forms\Components\Textarea::make('maksud_perjalanan')
                                ->label('Maksud Perjalanan Dinas (Opsional, kosongkan untuk default otomatis)')
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('tempat_berangkat')
                                ->label('Tempat Berangkat')
                                ->default('Demak')
                                ->maxLength(255)
                                ->required(),
                            Forms\Components\TextInput::make('tempat_tujuan')
                                ->label('Tempat Tujuan (Kosongkan jika mengikuti Surat Tugas)')
                                ->maxLength(255),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $settings = app(SystemSettings::class);
                            $count = 0;
                            $nextSppdUrut = $data['nomor_urut_sppd_mulai'] - 1;
                            
                            foreach ($records as $record) {
                                if (!$record->sppd()->exists()) {
                                    $year = \Carbon\Carbon::parse($record->tanggal)->year;
                                    $nextSppdUrut++;
                                    $urutSppdPad = str_pad($nextSppdUrut, 4, '0', STR_PAD_LEFT);
                                    $nomorSppd = str_replace('{urut}', $urutSppdPad, $data['format_nomor_sppd']);

                                    $record->sppd()->create([
                                        'nomor_sppd' => $nomorSppd,
                                        'nomor_urut_sppd' => $nextSppdUrut,
                                        'kode_klasifikasi_sppd' => 'KP.650',
                                        'tingkat_perjalanan_dinas' => $data['tingkat_perjalanan_dinas'],
                                        'alat_angkutan' => $data['alat_angkutan'],
                                        'mak' => $data['mak'],
                                        'maksud_perjalanan' => $data['maksud_perjalanan'] ?: "Transport lokal dalam rangka {$record->keperluan}",
                                        'tempat_berangkat' => $data['tempat_berangkat'] ?: 'Demak',
                                        'tempat_tujuan' => $data['tempat_tujuan'] ?: $record->tempat_tugas,
                                        'ppk_name' => $settings->ppk_name,
                                        'ppk_nip' => $settings->ppk_nip,
                                        'ppk_title' => $settings->ppk_title,
                                    ]);
                                    $count++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Berhasil!')
                                ->body("{$count} SPPD baru telah di-generate.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('syncPejabatBulk')
                        ->label('Update Pejabat Terpilih')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'kepala', 'kasubag']))
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $settings = app(SystemSettings::class);
                            $records->each(function ($record) use ($settings) {
                                $record->update([
                                    'signer_name' => $settings->cert_signer_name,
                                    'signer_nip' => $settings->cert_signer_nip,
                                    'signer_title' => $settings->cert_signer_title,
                                    'signer_city' => $settings->cert_city,
                                    'signer_signature_path' => $settings->cert_signer_signature_path,
                                ]);
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Berhasil!')
                                ->body("Data pejabat pada {$records->count()} surat tugas telah diperbarui ke pengaturan terbaru.")
                                ->success()
                                ->send();
                        }),
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

                            // Dispatch Job
                            \App\Jobs\GenerateBulkSuratTugasZip::dispatch($records->pluck('id')->toArray(), 'word', auth()->id());

                            \Filament\Notifications\Notification::make()
                                ->title('Proses Download Dimulai')
                                ->body('File sedang dikompresi di latar belakang. Anda akan menerima notifikasi di Ikon Lonceng jika file ZIP sudah siap didownload.')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('downloadPdfBulk')
                        ->label('Download PDF (Gabungan)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('danger')
                        ->modalHeading(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $pendingCount = $records->where('status', '!=', 'approved')->count();
                            if ($pendingCount > 0) {
                                return "⚠️ {$pendingCount} surat tugas belum di-approve";
                            }
                            return 'Download PDF';
                        })
                        ->modalDescription(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $pendingCount = $records->where('status', '!=', 'approved')->count();
                            if ($pendingCount > 0) {
                                return "Ada {$pendingCount} surat tugas yang belum di-approve. PDF yang belum approved tidak akan memiliki QR Code. Tetap download?";
                            }
                            return "Download {$records->count()} surat tugas sebagai satu file PDF gabungan?";
                        })
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            \App\Jobs\GenerateBulkSuratTugasZip::dispatch($records->pluck('id')->toArray(), 'pdf', auth()->id());

                            \Filament\Notifications\Notification::make()
                                ->title('Proses Download Dimulai')
                                ->body('File PDF sedang di-generate dan digabungkan di latar belakang. Anda akan menerima notifikasi di Ikon Lonceng jika file PDF sudah siap didownload.')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nomor_urut', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user && !$user->hasRole(['super_admin', 'Kasubag', 'Kepala', 'Operator', 'Ketua Tim'])) {
            // Ketua Tim: bisa lihat surat tugas yang mereka buat ATAU yang ditujukan untuk mereka
            // if ($user->hasRole('Ketua Tim')) {
            //     $query->where(function ($q) use ($user) {
            //         $q->where('created_by', $user->id)
            //             ->orWhere('user_id', $user->id);
            //     });
            // } else {
            // Pegawai biasa: hanya bisa lihat surat tugas untuk diri sendiri
            $query->where('user_id', $user->id);
            //}
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
            'manage-blocked-numbers' => Pages\ManageBlockedNumbers::route('/manage-blocked-numbers'),
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

    protected static function updateNomorSppd(Get $get, Set $set): void
    {
        $settings = app(SystemSettings::class);
        $prefix = $settings->surat_prefix ?? 'B';
        $office = $settings->office_code ?? '33210';

        $urut = str_pad($get('nomor_urut_sppd') ?? 0, 4, '0', STR_PAD_LEFT);
        $klasifikasi = $get('kode_klasifikasi_sppd') ?? 'KP.650';

        // Use tanggal year if available, else current year
        $tanggal = $get('tanggal');
        $year = $tanggal ? \Carbon\Carbon::parse($tanggal)->year : now()->year;

        $nomor = "{$prefix}-{$urut}/{$office}/SE2026/{$klasifikasi}/{$year}";
        $set('nomor_sppd', $nomor);
    }
}
