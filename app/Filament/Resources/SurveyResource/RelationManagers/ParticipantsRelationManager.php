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
use Filament\Resources\Components\Tab;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'surveyUsers';
    protected static ?string $title = 'Peserta';

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'mitra' => Tab::make('Mitra')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('user.roles', fn($q) => $q->where('name', 'Mitra'))),
            'organik' => Tab::make('Pegawai')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('user.roles', fn($q) => $q->where('name', 'Organik'))),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('User')
                ->options(User::orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()->required(),
            Forms\Components\Select::make('status')
                ->options(['registered' => 'registered', 'approved' => 'approved'])
                ->default('registered')->required(),
            Forms\Components\TextInput::make('score')
                ->numeric()->minValue(0)->maxValue(100)->nullable(),
            Forms\Components\Textarea::make('notes')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('status')->colors([
                    'warning' => 'registered',
                    'success' => 'approved',
                ]),
                Tables\Columns\TextColumn::make('score')->label('Skor')->numeric(),
                Tables\Columns\TextColumn::make('registered_at')->since()->label('Terdaftar'),
                Tables\Columns\IconColumn::make('certificate_exists')
                    ->label('Sertifikat')
                    ->state(fn(SurveyUser $r) => Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists())
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Tambah Peserta'),

                Action::make('massAdd')
                    ->label('Tambah Peserta (Banyak)')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Section::make('Peserta Survei')->schema([
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

                        Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
                    ])
                    ->action(function (array $data, $livewire) {
                        /** @var \App\Models\Survey $survey */
                        $survey = $livewire->getOwnerRecord(); // record parent (Survey)
                        $now = now();

                        // Merge users from both role categories
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

                        foreach ($allUsers as $userId) {
                            SurveyUser::firstOrCreate(
                                ['survey_id' => $survey->id, 'user_id' => $userId],
                                [
                                    'status' => 'registered',
                                    'registered_at' => $now,
                                    'notes' => $data['notes'] ?? null,
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
