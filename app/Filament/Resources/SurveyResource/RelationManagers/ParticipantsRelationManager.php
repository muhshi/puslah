<?php

namespace App\Filament\Resources\SurveyResource\RelationManagers;


use App\Models\Certificate;
use App\Models\SurveyUser;
use App\Models\User;
use App\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Services\CertificateIssuanceService;
use App\Exports\SurveyClassTemplateExport;
use App\Imports\SurveyClassAssignmentImport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Resources\Components\Tab;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'surveyUsers';
    protected static ?string $title = 'Peserta Kegiatan';

    public function getTabs(): array
    {
        /** @var \App\Models\Survey $survey */
        $survey = $this->getOwnerRecord();

        $tabs = [
            'semua' => Tab::make('Semua'),
            'mitra' => Tab::make('Mitra')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('user.roles', fn($q) => $q->where('name', 'Mitra'))),
            'organik' => Tab::make('Pegawai')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('user.roles', fn($q) => $q->where('name', 'Organik'))),
        ];

        if ($survey && $survey->is_multiclass) {
            $classes = $survey->classes()->withCount('surveyUsers')->get();
            foreach ($classes as $class) {
                $tabKey = 'class_' . $class->id;
                $tabs[$tabKey] = Tab::make($class->name)
                    ->badge($class->survey_users_count)
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('survey_class_id', $class->id));
            }

            $unassignedCount = SurveyUser::where('survey_id', $survey->id)->whereNull('survey_class_id')->count();
            if ($unassignedCount > 0) {
                $tabs['unassigned_class'] = Tab::make('Tanpa Kelas')
                    ->badge($unassignedCount)
                    ->badgeColor('warning')
                    ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('survey_class_id'));
            }
        }

        return $tabs;
    }

    public function form(Form $form): Form
    {
        $settings = app(SystemSettings::class);
        $roleOptions = $settings->getRoleOptions();

        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Peserta')
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()->required(),
            Forms\Components\Select::make('survey_class_id')
                ->label('Kelas Kegiatan')
                ->options(fn () => $this->getOwnerRecord()->classes()->pluck('name', 'id'))
                ->placeholder('Pilih kelas...')
                ->visible(fn () => (bool) $this->getOwnerRecord()->is_multiclass),
            Forms\Components\Select::make('role')
                ->label('Role / Peran')
                ->options($roleOptions)
                ->default('Peserta')
                ->helperText('Peran peserta pada kegiatan. Nama lengkap resmi akan otomatis dicetak pada sertifikat sesuai pemetaan pengaturan sistem.')
                ->required()
                ->createOptionForm([
                    Forms\Components\TextInput::make('code')
                        ->label('Singkatan / Nama Peran')
                        ->placeholder('Contoh: Inda, Koseka')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('full_name')
                        ->label('Nama Lengkap di Sertifikat')
                        ->placeholder('Contoh: Instruktur Daerah')
                        ->withProperCase()
                        ->helperText('Teks resmi yang akan dicetak pada sertifikat kegiatan.')
                        ->required()
                        ->maxLength(255),
                ])
                ->createOptionUsing(function (array $data) {
                    $code = trim($data['code']);
                    $fullName = trim($data['full_name']);
                    $settings = app(SystemSettings::class);
                    $map = $settings->kegiatan_roles_map ?? [];
                    $map[$code] = $fullName;
                    $settings->kegiatan_roles_map = $map;
                    $settings->kegiatan_roles = array_keys($map);
                    $settings->save();

                    return $code;
                }),
            Forms\Components\Select::make('status')
                ->options(['registered' => 'Terdaftar', 'approved' => 'Disetujui'])
                ->default('registered')->required(),
            Forms\Components\TextInput::make('score')
                ->numeric()->minValue(0)->maxValue(100)->nullable()->label('Skor'),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')->withProperCase()->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('class.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('success')
                    ->default('-')
                    ->visible(fn () => (bool) $this->getOwnerRecord()->is_multiclass)
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('status')->colors([
                    'warning' => 'registered',
                    'success' => 'approved',
                ]),
                Tables\Columns\TextColumn::make('score')->label('Skor')->numeric(),
                Tables\Columns\TextColumn::make('registered_at')->since()->label('Terdaftar'),
                Tables\Columns\IconColumn::make('certificate_exists')
                    ->label('Sertifikat')
                    ->state(fn (SurveyUser $r) => Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists())
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Tambah Peserta'),

                Action::make('manageClasses')
                    ->label('Kelola Kelas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('warning')
                    ->visible(fn () => (bool) $this->getOwnerRecord()->is_multiclass)
                    ->modalHeading('Kelola Kelas Kegiatan')
                    ->modalDescription('Tambahkan, sesuaikan, atau hapus kelas untuk kegiatan ini.')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Medium)
                    ->form([
                        Forms\Components\Repeater::make('classes')
                            ->label('Daftar Kelas')
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Kelas')
                                    ->placeholder('Misal: Kelas A, Kelas B')
                                    ->required()
                                    ->withProperCase(),
                                Forms\Components\TextInput::make('description')
                                    ->label('Keterangan / Ruangan')
                                    ->placeholder('Opsional (misal: Ruang Rapat Lt 2)')
                                    ->withProperCase(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Kelas Baru')
                            ->reorderable(false),
                    ])
                    ->fillForm(function () {
                        /** @var \App\Models\Survey $survey */
                        $survey = $this->getOwnerRecord();
                        return [
                            'classes' => $survey->classes()->get()->map(fn ($c) => [
                                'id' => $c->id,
                                'name' => $c->name,
                                'description' => $c->description,
                            ])->toArray(),
                        ];
                    })
                    ->action(function (array $data) {
                        /** @var \App\Models\Survey $survey */
                        $survey = $this->getOwnerRecord();
                        $submittedClasses = $data['classes'] ?? [];
                        $keepIds = [];

                        foreach ($submittedClasses as $item) {
                            if (!empty($item['id'])) {
                                $cls = $survey->classes()->find($item['id']);
                                if ($cls) {
                                    $cls->update([
                                        'name' => $item['name'],
                                        'description' => $item['description'] ?? null,
                                    ]);
                                    $keepIds[] = $cls->id;
                                }
                            } else {
                                $newCls = $survey->classes()->create([
                                    'name' => $item['name'],
                                    'description' => $item['description'] ?? null,
                                ]);
                                $keepIds[] = $newCls->id;
                            }
                        }

                        // Hapus kelas yang dihapus dari modal
                        $survey->classes()->whereNotIn('id', $keepIds)->delete();

                        Notification::make()
                            ->title('Daftar kelas berhasil diperbarui!')
                            ->success()
                            ->send();
                    }),

                Action::make('downloadTemplateKelas')
                    ->label('Download Template Kelas')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => (bool) $this->getOwnerRecord()->is_multiclass)
                    ->action(function () {
                        /** @var \App\Models\Survey $survey */
                        $survey = $this->getOwnerRecord();
                        $cleanName = \Illuminate\Support\Str::slug($survey->name, '_');
                        $filename = "Template_Pembagian_Kelas_{$cleanName}_" . now()->format('Ymd_His') . ".xlsx";

                        return Excel::download(new SurveyClassTemplateExport($survey), $filename);
                    }),

                Action::make('importKelas')
                    ->label('Import Pembagian Kelas')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->visible(fn () => (bool) $this->getOwnerRecord()->is_multiclass)
                    ->modalHeading('Import Pembagian Kelas Peserta (Excel)')
                    ->modalDescription('Unggah berkas Excel berisi kolom Nama, Email / Sobat ID, dan Kelas. Kelas baru akan dibuat otomatis jika belum ada.')
                    ->modalSubmitActionLabel('Proses Import & Petakan')
                    ->form([
                        Forms\Components\FileUpload::make('excel_file')
                            ->label('Berkas Excel (.xlsx / .csv)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->disk('local')
                            ->directory('temp-imports')
                            ->required()
                            ->helperText('Pastikan menggunakan format template yang telah diunduh dari menu "Download Template Kelas".'),
                    ])
                    ->action(function (array $data) {
                        /** @var \App\Models\Survey $survey */
                        $survey = $this->getOwnerRecord();
                        $filePath = storage_path('app/' . $data['excel_file']);

                        if (!file_exists($filePath)) {
                            Notification::make()
                                ->title('File tidak ditemukan')
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            $importer = new SurveyClassAssignmentImport($survey);
                            Excel::import($importer, $filePath);

                            // Bersihkan file sementara
                            @unlink($filePath);

                            $msg = "Berhasil memetakan {$importer->success} peserta ke dalam kelas.";
                            if ($importer->newClassesCount > 0) {
                                $msg .= " Terdaftar {$importer->newClassesCount} kelas baru yang otomatis dibuat.";
                            }
                            if ($importer->skipped > 0) {
                                $msg .= " (Dilewati: {$importer->skipped} baris yang tidak cocok dengan kegiatan ini).";
                            }

                            Notification::make()
                                ->title('Import Pembagian Kelas Berhasil! ✨')
                                ->body($msg)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            @unlink($filePath);
                            Notification::make()
                                ->title('Gagal mengimpor file')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('massAdd')
                    ->label('Tambah Peserta (Banyak)')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Section::make('Peserta Kegiatan')->schema([
                            Forms\Components\Select::make('mitra_users')
                                ->label('Pegawai Mitra')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(function () {
                                    return User::whereHas('roles', function ($query) {
                                        $query->where('name', 'Mitra');
                                    })
                                        ->with('profile')
                                        ->get()
                                        ->mapWithKeys(function ($user) {
                                            $jabatan = $user->profile->jabatan ?? '-';
                                            return [$user->id => "{$user->name} ({$jabatan})"];
                                        });
                                })
                                ->helperText('Cari dan pilih pegawai dengan role Mitra'),

                            Forms\Components\Select::make('pegawai_bps_users')
                                ->label('Pegawai Organik')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(function () {
                                    return User::whereHas('roles', function ($query) {
                                        $query->where('name', 'Organik');
                                    })
                                        ->with('profile')
                                        ->get()
                                        ->mapWithKeys(function ($user) {
                                            $jabatan = $user->profile->jabatan ?? '-';
                                            return [$user->id => "{$user->name} ({$jabatan})"];
                                        });
                                })
                                ->helperText('Cari dan pilih pegawai dengan role Organik'),
                        ])->columns(2),

                        Forms\Components\Select::make('survey_class_id')
                            ->label('Kelas Kegiatan (Opsional)')
                            ->options(fn () => $this->getOwnerRecord()->classes()->pluck('name', 'id'))
                            ->placeholder('Pilih kelas untuk langsung membagi peserta...')
                            ->visible(fn () => (bool) $this->getOwnerRecord()->is_multiclass)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('role')
                            ->label('Role / Peran untuk semua peserta di atas')
                            ->options(fn () => app(SystemSettings::class)->getRoleOptions())
                            ->default('Peserta')
                            ->helperText('Peran peserta pada kegiatan. Nama lengkap resmi akan otomatis dicetak pada sertifikat sesuai pemetaan pengaturan sistem.')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')->label('Catatan')->withProperCase()->columnSpanFull(),
                    ])
                    ->action(function (array $data, $livewire) {
                        /** @var \App\Models\Survey $survey */
                        $survey = $livewire->getOwnerRecord();
                        $now = now();

                        $mitraUsers   = $data['mitra_users'] ?? [];
                        $pegawaiUsers = $data['pegawai_bps_users'] ?? [];
                        $allUsers     = array_merge($mitraUsers, $pegawaiUsers);

                        if (empty($allUsers)) {
                            Notification::make()->title('Pilih minimal 1 peserta')->danger()->send();
                            return;
                        }

                        $classId = !empty($data['survey_class_id']) ? (int) $data['survey_class_id'] : null;

                        foreach ($allUsers as $userId) {
                            SurveyUser::firstOrCreate(
                                ['survey_id' => $survey->id, 'user_id' => $userId],
                                [
                                    'survey_class_id' => $classId,
                                    'role'            => $data['role'] ?? 'Peserta',
                                    'status'          => 'registered',
                                    'registered_at'   => $now,
                                    'notes'           => $data['notes'] ?? null,
                                ]
                            );
                        }

                        Notification::make()
                            ->title('Peserta ditambahkan: ' . count($allUsers) . ' orang')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([

                Tables\Actions\EditAction::make(),
                Action::make('approve')
                    ->label('Approve + Sertifikat')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn(SurveyUser $r) => $r->status !== 'approved')
                    ->action(fn(SurveyUser $r) => $this->approveOne($r)),
                Action::make('unapprove')
                    ->label('Batal Approve')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(SurveyUser $r) => $r->status === 'approved')
                    ->action(fn(SurveyUser $r) => $this->unapproveOne($r)),
                Action::make('downloadCert')
                    ->label('Unduh Sertifikat')
                    ->url(function (SurveyUser $r) {
                        $cert = Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->first();
                        return $cert ? route('certificates.download', $cert) : null;
                    })
                    ->openUrlInNewTab()
                    ->visible(fn(SurveyUser $r) => Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('assignToClass')
                    ->label('Assign ke Kelas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('primary')
                    ->visible(fn () => (bool) $this->getOwnerRecord()->is_multiclass)
                    ->form([
                        Forms\Components\Select::make('survey_class_id')
                            ->label('Pilih Kelas Tujuan')
                            ->options(fn () => [
                                '' => '— Kosongkan Kelas (Tanpa Kelas) —',
                            ] + $this->getOwnerRecord()->classes()->pluck('name', 'id')->toArray()),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $classId = !empty($data['survey_class_id']) ? (int) $data['survey_class_id'] : null;
                        foreach ($records as $row) {
                            $row->update(['survey_class_id' => $classId]);
                        }

                        $className = $classId
                            ? ($this->getOwnerRecord()->classes()->find($classId)?->name ?? 'Kelas')
                            : 'Tanpa Kelas';

                        Notification::make()
                            ->title("Berhasil assign {$records->count()} peserta ke {$className}")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('approveSelected')
                    ->label('Approve & Terbitkan Sertifikat')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        try {
                            foreach ($records as $row) { // $row adalah SurveyUser
                                if ($row->status !== 'approved') {
                                    $row->update(['status' => 'approved']);
                                    $this->issueCertificate($row); // pakai helper milik RelationManager-mu
                                    // atau dispatch(Job) di sini
                                }
                            }
                            Notification::make()->title('Approved & sertifikat diterbitkan')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                            Log::error('RM bulk approve error', ['e' => $e]);
                        }
                    }),
                Tables\Actions\BulkAction::make('unapproveSelected')
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
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('survey_users.id', 'desc');
    }

    protected function approveOne(SurveyUser $r): void
    {
        try {
            // Check if certificate already exists or can be created
            if (Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists()) {
                // Certificate exists, just update status
                $r->update(['status' => 'approved']);
                Notification::make()->title('Approved & sertifikat sudah ada')->success()->send();
                return;
            }

            // Check if template exists
            $template = \App\Models\CertificateTemplate::where('active', 1)->first();
            if (!$template) {
                Notification::make()
                    ->title('Gagal: Tidak ada template sertifikat yang aktif')
                    ->danger()
                    ->send();
                return;
            }

            // Try to create certificate
            $this->issueCertificate($r);
            $r->update(['status' => 'approved']);
            Notification::make()->title('Approved & sertifikat terbit')->success()->send();

        } catch (\Throwable $e) {
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
        // Optional: Delete certificate?
        // For now, just change status. Certificate remains record but status reverts.
        Notification::make()->title('Approval dibatalkan')->success()->send();
    }

    /** Generate nomor + PDF + simpan Certificate */
    protected function issueCertificate(SurveyUser $row): void
    {
        app(CertificateIssuanceService::class)->issue($row);
    }
}
