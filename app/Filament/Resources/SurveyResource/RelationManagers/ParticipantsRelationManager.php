<?php

namespace App\Filament\Resources\SurveyResource\RelationManagers;

use App\Exports\PelatihanKelasTemplateExport;
use App\Exports\SurveyClassTemplateExport;
use App\Imports\PelatihanKelasImport;
use App\Imports\SurveyClassAssignmentImport;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Survey;
use App\Models\SurveyClass;
use App\Models\SurveyUser;
use App\Models\User;
use App\Services\CertificateIssuanceService;
use App\Services\SipetraUserDataService;
use App\Settings\SystemSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'surveyUsers';

    protected static ?string $title = 'Peserta';

    public function getTabs(): array
    {
        $tabs = [
            'semua' => Tab::make('Semua'),
            'mitra' => Tab::make('Mitra')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('user.roles', fn ($q) => $q->where('name', 'Mitra'))),
            'organik' => Tab::make('Pegawai')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('user.roles', fn ($q) => $q->where('name', 'Organik'))),
        ];

        $owner = $this->getOwnerRecord();
        if ($owner && $owner->is_multiclass) {
            foreach ($owner->classes()->orderBy('name')->get() as $class) {
                $tabs['class_' . $class->id] = Tab::make($class->name)
                    ->badge($class->surveyUsers()->count())
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('survey_class_id', $class->id));
            }
            $tabs['tanpa_kelas'] = Tab::make('Tanpa Kelas')
                ->badge($owner->surveyUsers()->whereNull('survey_class_id')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('survey_class_id'));
        }

        return $tabs;
    }

    public function form(Schema $schema): Schema
    {
        $isPelatihan = fn ($livewire) => (bool) ($livewire?->getOwnerRecord()?->is_pelatihan ?? false);
        $isMulticlass = fn ($livewire) => (bool) ($livewire?->getOwnerRecord()?->is_multiclass ?? false);

        return $schema->components([
            Select::make('user_id')
                ->label('User')
                ->options(fn () => static::userOptionLabels())
                ->searchable()
                ->preload()
                ->required(),
            Select::make('status')
                ->options(['registered' => 'registered', 'approved' => 'approved'])
                ->default('registered')
                ->required(),
            TextInput::make('score')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->nullable(),
            Select::make('role')
                ->label('Peran / Jabatan Kegiatan')
                ->options(function () {
                    try {
                        $map = app(SystemSettings::class)->kegiatan_roles_map ?? [];
                        if (! empty($map)) {
                            return collect($map)->mapWithKeys(fn ($full, $code) => [$code => "{$code} ({$full})"])->toArray();
                        }
                    } catch (Throwable) {
                    }

                    return [
                        'PML' => 'PML',
                        'PCL' => 'PCL',
                        'Inda' => 'Inda',
                        'Panitia' => 'Panitia',
                        'Penanggung Jawab' => 'Penanggung Jawab',
                        'Peserta' => 'Peserta',
                    ];
                })
                ->searchable()
                ->default('Peserta')
                ->visible(fn ($livewire) => ! $isPelatihan($livewire)),
            Select::make('survey_class_id')
                ->label('Kelas (Multi-Kelas)')
                ->options(function ($livewire) {
                    $owner = $livewire?->getOwnerRecord();

                    return $owner ? $owner->classes()->orderBy('name')->pluck('name', 'id') : [];
                })
                ->searchable()
                ->preload()
                ->nullable()
                ->visible($isMulticlass),
            Select::make('role_pelatihan')
                ->label('Role Pelatihan')
                ->options([
                    'PPL' => 'PPL',
                    'PML' => 'PML',
                    'Inda' => 'Inda',
                    'Panitia' => 'Panitia',
                ])
                ->default('PPL')
                ->visible($isPelatihan),
            Select::make('kelas')
                ->label('Kelas')
                ->options([
                    'Kelas A' => 'Kelas A',
                    'Kelas B' => 'Kelas B',
                    'Kelas C' => 'Kelas C',
                    'Kelas D' => 'Kelas D',
                ])
                ->default('Kelas A')
                ->visible($isPelatihan),
            TextInput::make('asal')
                ->label('Asal')
                ->placeholder('Contoh: Demak')
                ->visible($isPelatihan),
            TextInput::make('tujuan')
                ->label('Tujuan')
                ->placeholder('Contoh: Semarang')
                ->default(fn ($livewire) => $livewire?->getOwnerRecord()?->kota_kegiatan ?? 'Semarang')
                ->visible($isPelatihan),
            TextInput::make('transport_override')
                ->label('Override Transport (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->nullable()
                ->visible($isPelatihan),
            TextInput::make('uang_harian_override')
                ->label('Override Uang Harian (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->nullable()
                ->visible($isPelatihan),
            Textarea::make('notes')
                // ->withProperCase()
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $isPelatihan = fn ($livewire) => (bool) ($livewire?->getOwnerRecord()?->is_pelatihan ?? false);
        $isMulticlass = fn ($livewire) => (bool) ($livewire?->getOwnerRecord()?->is_multiclass ?? false);

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama')
                    ->description(fn (SurveyUser $r): ?string => $r->user?->email)
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->color('info')
                    ->visible(fn ($livewire) => ! $isPelatihan($livewire)),
                TextColumn::make('class.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Tanpa Kelas')
                    ->visible(fn ($livewire) => $isMulticlass($livewire) && ! $isPelatihan($livewire)),
                SelectColumn::make('role_pelatihan')
                    ->label('Role')
                    ->options([
                        'PPL' => 'PPL',
                        'PML' => 'PML',
                        'Inda' => 'Inda',
                        'Panitia' => 'Panitia',
                    ])
                    ->sortable()
                    ->visible($isPelatihan),
                SelectColumn::make('kelas')
                    ->label('Kelas')
                    ->options([
                        'Kelas A' => 'Kelas A',
                        'Kelas B' => 'Kelas B',
                        'Kelas C' => 'Kelas C',
                        'Kelas D' => 'Kelas D',
                    ])
                    ->sortable()
                    ->visible($isPelatihan),
                TextInputColumn::make('asal')
                    ->label('Asal')
                    ->visible($isPelatihan),
                TextInputColumn::make('tujuan')
                    ->label('Tujuan')
                    ->visible($isPelatihan),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'registered',
                        'success' => 'approved',
                    ]),
                TextColumn::make('score')
                    ->label('Skor')
                    ->numeric(),
                TextColumn::make('registered_at')
                    ->since()
                    ->label('Terdaftar'),
                IconColumn::make('certificate_exists')
                    ->label('Sertifikat')
                    ->state(fn (SurveyUser $r) => Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists())
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kelas')
                    ->label('Kelas')
                    ->options([
                        'Kelas A' => 'Kelas A',
                        'Kelas B' => 'Kelas B',
                        'Kelas C' => 'Kelas C',
                        'Kelas D' => 'Kelas D',
                    ])
                    ->visible($isPelatihan),
                SelectFilter::make('role_pelatihan')
                    ->label('Role Pelatihan')
                    ->options([
                        'PPL' => 'PPL',
                        'PML' => 'PML',
                        'Inda' => 'Inda',
                        'Panitia' => 'Panitia',
                    ])
                    ->visible($isPelatihan),
            ])
            ->headerActions([
                Action::make('manageClasses')
                    ->label('Kelola Kelas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('warning')
                    ->visible($isMulticlass)
                    ->modalHeading('Kelola Kelas Kegiatan')
                    ->fillForm(function ($livewire) {
                        /** @var Survey $survey */
                        $survey = $livewire->getOwnerRecord();

                        return [
                            'classes' => $survey->classes()->orderBy('name')->get()->map(fn ($c) => [
                                'id' => $c->id,
                                'name' => $c->name,
                                'description' => $c->description,
                            ])->toArray(),
                        ];
                    })
                    ->schema([
                        Repeater::make('classes')
                            ->label('Daftar Kelas')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Kelas')
                                    ->placeholder('Contoh: Kelas A')
                                    ->required(),
                                TextInput::make('description')
                                    ->label('Keterangan (Opsional)'),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ Tambah Kelas')
                            ->defaultItems(1),
                    ])
                    ->action(function (array $data, $livewire): void {
                        /** @var Survey $survey */
                        $survey = $livewire->getOwnerRecord();
                        $items = $data['classes'] ?? [];
                        $keptIds = [];

                        foreach ($items as $item) {
                            $name = trim((string) ($item['name'] ?? ''));
                            if ($name === '') {
                                continue;
                            }
                            if (! empty($item['id'])) {
                                $class = SurveyClass::where('survey_id', $survey->id)->find($item['id']);
                                if ($class) {
                                    $class->update([
                                        'name' => $name,
                                        'description' => $item['description'] ?? null,
                                    ]);
                                    $keptIds[] = $class->id;
                                    continue;
                                }
                            }
                            $newClass = $survey->classes()->create([
                                'name' => $name,
                                'description' => $item['description'] ?? null,
                            ]);
                            $keptIds[] = $newClass->id;
                        }

                        // Hapus kelas yang dibuang dari repeater (peserta menjadi Tanpa Kelas)
                        $survey->classes()->whereNotIn('id', $keptIds)->delete();

                        Notification::make()
                            ->title('Daftar kelas diperbarui')
                            ->success()
                            ->send();
                    }),

                Action::make('massAdd')
                    ->label('Tambah Peserta (Banyak)')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Section::make('Peserta Survei')->schema([
                            Select::make('mitra_users')
                                ->label('Pegawai Mitra')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(fn () => static::userOptionLabels('Mitra'))
                                ->helperText('Cari berdasarkan nama, email, atau kecamatan mitra'),

                            Select::make('pegawai_bps_users')
                                ->label('Pegawai Organik')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(fn () => static::userOptionLabels('Organik'))
                                ->helperText('Cari berdasarkan nama, jabatan, atau email pegawai Organik'),
                        ])->columns(2),

                        Textarea::make('notes')->label('Catatan')->columnSpanFull(),
                    ])
                    ->action(function (array $data, $livewire) {
                        /** @var Survey $survey */
                        $survey = $livewire->getOwnerRecord();
                        $now = now();

                        $mitraUsers = $data['mitra_users'] ?? [];
                        $pegawaiUsers = $data['pegawai_bps_users'] ?? [];
                        $allUsers = array_merge($mitraUsers, $pegawaiUsers);

                        if (empty($allUsers)) {
                            Notification::make()
                                ->title('Pilih minimal 1 peserta')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($survey->is_pelatihan) {
                            $users = User::with('profile')->whereIn('id', $allUsers)->get()->keyBy('id');
                            $sipetraService = app(SipetraUserDataService::class);
                            $tujuanDefault = $survey->kota_kegiatan ?: 'Semarang';

                            foreach ($allUsers as $userId) {
                                $user = $users->get($userId);
                                $isMitra = in_array($userId, $mitraUsers);
                                $rolePelatihan = $isMitra ? 'PPL' : 'PML';
                                $asal = $sipetraService->extractAsal(null, $user, 'Demak');

                                SurveyUser::firstOrCreate(
                                    ['survey_id' => $survey->id, 'user_id' => $userId],
                                    [
                                        'status' => 'registered',
                                        'registered_at' => $now,
                                        'notes' => $data['notes'] ?? null,
                                        'role' => $rolePelatihan,
                                        'role_pelatihan' => $rolePelatihan,
                                        'kelas' => 'Kelas A',
                                        'asal' => $asal,
                                        'tujuan' => $tujuanDefault,
                                    ]
                                );
                            }
                        } else {
                            foreach ($allUsers as $userId) {
                                SurveyUser::firstOrCreate(
                                    ['survey_id' => $survey->id, 'user_id' => $userId],
                                    [
                                        'status' => 'registered',
                                        'registered_at' => $now,
                                        'notes' => $data['notes'] ?? null,
                                        'role' => 'Peserta',
                                    ]
                                );
                            }
                        }

                        Notification::make()
                            ->title('Peserta ditambahkan: '.count($allUsers).' orang')
                            ->success()
                            ->send();
                    }),

                Action::make('downloadClassTemplate')
                    ->label('Download Template Kelas')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($livewire) => $isMulticlass($livewire) && ! $isPelatihan($livewire))
                    ->action(function ($livewire) {
                        /** @var Survey $survey */
                        $survey = $livewire->getOwnerRecord();
                        $filename = 'Template_Kelas_' . Str::slug($survey->name ?: 'Kegiatan') . '.xlsx';

                        return Excel::download(new SurveyClassTemplateExport($survey), $filename);
                    }),

                Action::make('importClassAssignment')
                    ->label('Import Pembagian Kelas')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(fn ($livewire) => $isMulticlass($livewire) && ! $isPelatihan($livewire))
                    ->schema([
                        FileUpload::make('file')
                            ->label('File Excel Pembagian Kelas')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->disk('local')
                            ->directory('temp-imports')
                            ->required(),
                    ])
                    ->action(function (array $data, $livewire): void {
                        /** @var Survey $survey */
                        $survey = $livewire->getOwnerRecord();
                        $filePath = Storage::disk('local')->path($data['file']);

                        $import = new SurveyClassAssignmentImport($survey);
                        Excel::import($import, $filePath);

                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title('Import Pembagian Kelas Selesai')
                            ->body("Diperbarui: {$import->updatedCount}, Dilewati: {$import->skippedCount}")
                            ->success()
                            ->send();
                    }),

                Action::make('exportKelasTemplate')
                    ->label('Download Template Kelas')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible($isPelatihan)
                    ->action(fn ($livewire) => static::downloadKelasTemplate($livewire->getOwnerRecord())),

                Action::make('importKelas')
                    ->label('Import Pembagian Kelas')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible($isPelatihan)
                    ->modalDescription('Tidak perlu memilih peserta satu per satu & tidak perlu hafal ID. Download template Excel, isi Nama/Email beserta Role & Kelas, lalu upload kembali di sini. Khusus untuk nama petugas yang kembar, isi kolom Email agar tidak tertukar. Jika peserta sudah pernah ditambahkan via web, datanya otomatis diperbarui tanpa duplikat.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('File Excel Format Kelas')
                            ->hintAction(
                                Action::make('downloadTemplateInModal')
                                    ->label('Download Template (.xlsx)')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->action(fn ($livewire) => static::downloadKelasTemplate($livewire->getOwnerRecord()))
                            )
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->disk('local')
                            ->directory('temp-imports')
                            ->required(),
                    ])
                    ->action(function (array $data, $livewire): void {
                        /** @var Survey $survey */
                        $survey = $livewire->getOwnerRecord();
                        $filePath = Storage::disk('local')->path($data['file']);

                        $import = new PelatihanKelasImport($survey);
                        Excel::import($import, $filePath);

                        Storage::disk('local')->delete($data['file']);

                        $summary = "Berhasil: {$import->success} (Baru: {$import->created}, Diperbarui: {$import->updated}), Dilewati: {$import->skipped}, Gagal: {$import->failed}";
                        if (! empty($import->errors)) {
                            $detail = implode(' | ', array_slice($import->errors, 0, 3));
                            if (count($import->errors) > 3) {
                                $detail .= ' (+'.(count($import->errors) - 3).' lainnya)';
                            }
                            $summary .= "\nCatatan: {$detail}";
                        }

                        $notification = Notification::make()
                            ->title('Import Pembagian Kelas Selesai')
                            ->body($summary);

                        if ($import->failed > 0) {
                            $notification->warning();
                        } else {
                            $notification->success();
                        }

                        $notification->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve + Sertifikat')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (SurveyUser $r) => $r->status !== 'approved')
                    ->action(fn (SurveyUser $r) => $this->approveOne($r)),
                Action::make('unapprove')
                    ->label('Batal Approve')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SurveyUser $r) => $r->status === 'approved')
                    ->action(fn (SurveyUser $r) => $this->unapproveOne($r)),
                Action::make('downloadCert')
                    ->label('Unduh Sertifikat')
                    ->url(function (SurveyUser $r) {
                        $cert = Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->first();

                        return $cert ? route('certificates.download', $cert) : null;
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (SurveyUser $r) => Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('assignClass')
                    ->label('Assign ke Kelas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('warning')
                    ->visible(fn ($livewire) => $isMulticlass($livewire) && ! $isPelatihan($livewire))
                    ->schema([
                        Select::make('survey_class_id')
                            ->label('Pilih Kelas Tujuan')
                            ->options(function ($livewire) {
                                $owner = $livewire->getOwnerRecord();

                                return $owner ? $owner->classes()->orderBy('name')->pluck('name', 'id') : [];
                            })
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $count = 0;
                        foreach ($records as $record) {
                            $record->update(['survey_class_id' => $data['survey_class_id']]);
                            $count++;
                        }
                        Notification::make()
                            ->title("Berhasil memindahkan {$count} peserta ke kelas terpilih")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('setKelas')
                    ->label('Set Kelas')
                    ->icon('heroicon-o-academic-cap')
                    ->visible($isPelatihan)
                    ->schema([
                        Select::make('kelas')
                            ->label('Pilih Kelas')
                            ->options([
                                'Kelas A' => 'Kelas A',
                                'Kelas B' => 'Kelas B',
                                'Kelas C' => 'Kelas C',
                                'Kelas D' => 'Kelas D',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $count = 0;
                        foreach ($records as $record) {
                            $record->update(['kelas' => $data['kelas']]);
                            $count++;
                        }
                        Notification::make()
                            ->title("Berhasil mengatur kelas untuk {$count} peserta")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('setRolePelatihan')
                    ->label('Set Role')
                    ->icon('heroicon-o-user-group')
                    ->visible($isPelatihan)
                    ->schema([
                        Select::make('role_pelatihan')
                            ->label('Pilih Role')
                            ->options([
                                'PPL' => 'PPL',
                                'PML' => 'PML',
                                'Inda' => 'Inda',
                                'Panitia' => 'Panitia',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $count = 0;
                        foreach ($records as $record) {
                            $record->update([
                                'role_pelatihan' => $data['role_pelatihan'],
                                'role' => $data['role_pelatihan'],
                            ]);
                            $count++;
                        }
                        Notification::make()
                            ->title("Berhasil mengatur role untuk {$count} peserta")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('setAsalTujuan')
                    ->label('Set Asal/Tujuan')
                    ->icon('heroicon-o-map-pin')
                    ->visible($isPelatihan)
                    ->schema([
                        TextInput::make('asal')
                            ->label('Asal')
                            ->placeholder('Contoh: Demak'),
                        TextInput::make('tujuan')
                            ->label('Tujuan')
                            ->placeholder('Contoh: Semarang'),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $updates = [];
                        if (! empty($data['asal'])) {
                            $updates['asal'] = $data['asal'];
                        }
                        if (! empty($data['tujuan'])) {
                            $updates['tujuan'] = $data['tujuan'];
                        }
                        if (empty($updates)) {
                            return;
                        }

                        $count = 0;
                        foreach ($records as $record) {
                            $record->update($updates);
                            $count++;
                        }
                        Notification::make()
                            ->title("Berhasil memperbarui asal/tujuan {$count} peserta")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('approveSelected')
                    ->label('Approve & Terbitkan Sertifikat')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        try {
                            foreach ($records as $row) {
                                if ($row->status !== 'approved') {
                                    $row->update(['status' => 'approved']);
                                    $this->issueCertificate($row);
                                }
                            }
                            Notification::make()->title('Approved & sertifikat diterbitkan')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                            Log::error('RM bulk approve error', ['e' => $e]);
                        }
                    }),

                BulkAction::make('unapproveSelected')
                    ->label('Batal Approve (Bulk)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $count = 0;
                        foreach ($records as $row) {
                            if ($row->status === 'approved') {
                                $row->update(['status' => 'registered']);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("Berhasil membatalkan approval {$count} peserta")
                            ->success()
                            ->send();
                    }),

                DeleteBulkAction::make(),
            ])
            ->defaultSort('survey_users.id', 'desc');
    }

    protected function approveOne(SurveyUser $r): void
    {
        try {
            if (Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists()) {
                $r->update(['status' => 'approved']);
                Notification::make()->title('Approved & sertifikat sudah ada')->success()->send();

                return;
            }

            $template = CertificateTemplate::where('active', 1)->first();
            if (! $template) {
                Notification::make()
                    ->title('Gagal: Tidak ada template sertifikat yang aktif')
                    ->danger()
                    ->send();

                return;
            }

            $this->issueCertificate($r);
            $r->update(['status' => 'approved']);
            Notification::make()->title('Approved & sertifikat terbit')->success()->send();

        } catch (Throwable $e) {
            Notification::make()
                ->title('Error saat menerbitkan sertifikat')
                ->body($e->getMessage())
                ->danger()
                ->send();
            Log::error('Certificate issuance error in RM', ['error' => $e, 'survey_user_id' => $r->id]);
        }
    }

    protected function unapproveOne(SurveyUser $r): void
    {
        $r->update(['status' => 'registered']);
        Notification::make()->title('Approval dibatalkan')->success()->send();
    }

    /** Generate nomor + PDF + simpan Certificate */
    protected function issueCertificate(SurveyUser $row): void
    {
        app(CertificateIssuanceService::class)->issue($row);
    }

    /**
     * Format user select labels with email and kecamatan/jabatan so duplicate names stay distinguishable.
     */
    protected static function userOptionLabels(?string $role = null): Collection
    {
        $query = User::query()
            ->with('profile')
            ->orderBy('name')
            ->orderBy('email');

        if ($role !== null) {
            $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
        }

        $sipetraService = app(SipetraUserDataService::class);

        return $query->get()->mapWithKeys(function (User $user) use ($role, $sipetraService): array {
            $kecamatan = $role !== 'Organik'
                ? $sipetraService->extractAsal(null, $user, '')
                : '';

            $parts = array_filter([
                $role === 'Organik' ? ($user->profile?->jabatan ?: null) : null,
                $user->email ?: null,
                filled($kecamatan) ? 'Kec. '.$kecamatan : null,
            ]);

            $label = empty($parts)
                ? $user->name
                : "{$user->name} (".implode(' · ', $parts).')';

            return [$user->id => $label];
        });
    }

    protected static function downloadKelasTemplate(Survey $survey): mixed
    {
        $filename = 'Template_Kelas_'.Str::slug($survey->name ?: 'Pelatihan').'.xlsx';

        return Excel::download(
            new PelatihanKelasTemplateExport($survey),
            $filename
        );
    }
}
