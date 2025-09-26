<?php

namespace App\Filament\Resources\SurveyResource\RelationManagers;


use App\Models\Certificate;
use App\Models\SurveyUser;
use App\Models\User;
use App\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
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

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';
    protected static ?string $title = 'Peserta';

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
            ->recordTitleAttribute('user.name')
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
                        Forms\Components\Select::make('users')
                            ->label('Pilih User')
                            ->options(User::orderBy('name')->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
                    ])
                    ->action(function (array $data, $livewire) {
                        /** @var \App\Models\Survey $survey */
                        $survey = $livewire->getOwnerRecord(); // record parent (Survey)
                        $now = Carbon::now('Asia/Jakarta');

                        foreach ($data['users'] as $userId) {
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
                            ->title('Peserta ditambahkan')
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
                    ->action(fn(SurveyUser $r) => $this->approveOne($r)),
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
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    protected function approveOne(SurveyUser $r): void
    {
        $r->update(['status' => 'approved']);
        $this->issueCertificate($r);
        Notification::make()->title('Approved & sertifikat terbit')->success()->send();
    }

    /** Generate nomor + PDF + simpan Certificate */
    protected function issueCertificate(SurveyUser $row): void
    {
        // Cegah dobel
        if (Certificate::where('survey_id', $row->survey_id)->where('user_id', $row->user_id)->exists())
            return;

        $cfg = app(SystemSettings::class);
        $now = Carbon::now('Asia/Jakarta');
        $y = $now->year;
        $m = str_pad($now->month, 2, '0', STR_PAD_LEFT);

        // Ambil & tingkatkan sequence per tahun
        $seqByYear = $cfg->cert_number_seq_by_year ?? [];
        $next = ($seqByYear[$y] ?? 0) + 1;
        $seqByYear[$y] = $next;
        $cfg->cert_number_seq_by_year = $seqByYear;
        $cfg->save();

        $seq6 = str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $no = "{$cfg->cert_number_prefix}/{$y}/{$m}/{$seq6}";

        // QR
        $verifyUrl = route('certificates.verify', ['no' => $no]);
        $qrPng = QrCode::format('png')->size(220)->margin(0)->generate($verifyUrl);
        $qrPath = "certificates/qr/{$y}{$m}-{$row->user_id}-{$row->survey_id}.png";
        Storage::put($qrPath, $qrPng);

        // Render PDF (pakai view sederhana; bisa ganti template nanti)
        $user = $row->user;
        $survey = $row->survey;
        $pdf = Pdf::loadView('certificates.simple', [
            'no' => $no,
            'user' => $user,
            'survey' => $survey,
            'issuedAt' => $now,
            'signerName' => $cfg->cert_signer_name,
            'signerTitle' => $cfg->cert_signer_title,
            'signPath' => $cfg->cert_signer_signature_path,
            'qrPath' => storage_path('app/' . $qrPath),
        ])->setPaper('a4', 'landscape');

        $pdfPath = "certificates/pdf/{$y}{$m}-{$row->user_id}-{$row->survey_id}.pdf";
        Storage::put($pdfPath, $pdf->output());

        // Hash isi
        $hash = hash('sha256', Storage::get($pdfPath));

        Certificate::create([
            'survey_id' => $row->survey_id,
            'user_id' => $row->user_id,
            'certificate_no' => $no,
            'issued_at' => $now,
            'file_path' => $pdfPath,
            'hash' => $hash,
        ]);
    }
}
