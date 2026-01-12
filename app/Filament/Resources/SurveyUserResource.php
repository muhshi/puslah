<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyUserResource\Pages;
use App\Filament\Resources\SurveyUserResource\RelationManagers;
use App\Models\Certificate;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Settings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SurveyUserResource extends Resource
{
    protected static ?string $model = SurveyUser::class;

    protected static ?string $navigationIcon = 'heroicon-m-user-group';
    protected static ?string $navigationGroup = 'Manajemen Survei';
    protected static ?string $navigationLabel = 'Peserta Survei';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('survey_id')
                ->label('Survei')
                ->options(fn() => Survey::orderBy('name')->pluck('name', 'id'))->searchable()->required(),

            Forms\Components\Select::make('user_id')
                ->label('Peserta')
                ->options(fn() => User::orderBy('name')->pluck('name', 'id'))->searchable()->required(),

            Forms\Components\Select::make('status')
                ->options(['registered' => 'registered', 'approved' => 'approved'])
                ->default('registered')->required(),

            Forms\Components\TextInput::make('score')->numeric()->minValue(0)->maxValue(100)->nullable(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('survey.name')->label('Survei')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Peserta')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => $state === 'approved' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('score')->numeric()->label('Skor')->sortable(),
                Tables\Columns\TextColumn::make('registered_at')->date()->label('Terdaftar')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('survey_id')->label('Survei')
                    ->options(fn() => Survey::orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['registered' => 'registered', 'approved' => 'approved']),
                Tables\Filters\Filter::make('registered_at')->form([
                    Forms\Components\DatePicker::make('from'),
                    Forms\Components\DatePicker::make('until'),
                ])->query(function ($query, array $data) {
                    return $query
                        ->when($data['from'] ?? null, fn($qq, $d) => $qq->whereDate('registered_at', '>=', $d))
                        ->when($data['until'] ?? null, fn($qq, $d) => $qq->whereDate('registered_at', '<=', $d));
                }),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make()->label('Tambah Peserta'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approveSelected')
                    ->label('Approve & Terbitkan Sertifikat')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion() // UI rapi setelah selesai
                    ->action(function (Collection $records): void {
                        try {
                            $records->each(function (\App\Models\SurveyUser $row) {
                                if ($row->status !== 'approved') {
                                    $row->update(['status' => 'approved']);
                                    // Ideal: dispatch(new IssueCertificateJob($row->id));
                                    self::issueCertificate($row); // sementara tetap sinkron
                                }
                            });

                            Notification::make()
                                ->title('Approved & sertifikat diterbitkan')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal menerbitkan sebagian/semua sertifikat')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            // log biar tahu akar masalah
                            Log::error('Bulk approve error', ['e' => $e]);
                        }
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('approve')
                    ->label('Approve + Sertifikat')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn(SurveyUser $r) => $r->status !== 'approved')
                    ->action(function (SurveyUser $r) {
                        $r->update(['status' => 'approved']);
                        self::issueCertificate($r);
                    }),
                Action::make('unapprove')
                    ->label('Batal Approve')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(SurveyUser $r) => $r->status === 'approved')
                    ->action(function (SurveyUser $r) {
                        $r->update(['status' => 'registered']);
                        Notification::make()->title('Approval dibatalkan')->success()->send();
                    }),
                Action::make('downloadCert')
                    ->label('Unduh Sertifikat')
                    ->url(function (SurveyUser $r) {
                        $cert = Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->first();
                        return $cert ? route('certificates.download', $cert) : null;
                    })
                    ->openUrlInNewTab()
                    ->visible(fn(SurveyUser $r) => Certificate::where('survey_id', $r->survey_id)->where('user_id', $r->user_id)->exists()),
            ])
            ->defaultSort('id', 'desc');
    }

    protected static function issueCertificate(SurveyUser $row): void
    {
        if (Certificate::where('survey_id', $row->survey_id)->where('user_id', $row->user_id)->exists())
            return;

        // 1. Get Active Template
        $template = \App\Models\CertificateTemplate::where('active', true)->first();
        if (!$template) {
            Notification::make()->title('Gagal: Tidak ada template sertifikat yang aktif.')->danger()->send();
            return;
        }

        $cfg = app(SystemSettings::class);
        $now = Carbon::now('Asia/Jakarta');
        $y = $now->year;
        $m = str_pad($now->month, 2, '0', STR_PAD_LEFT);

        // 2. Generate Number
        $seqByYear = $cfg->cert_number_seq_by_year ?? [];
        $next = ($seqByYear[$y] ?? 0) + 1;
        $seqByYear[$y] = $next;
        $cfg->cert_number_seq_by_year = $seqByYear;
        $cfg->save();

        $seq6 = str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $no = "{$cfg->cert_number_prefix}/{$y}/{$m}/{$seq6}";

        // 3. Prepare Assets (Base64) for PDF
        // Background
        $bgBase64 = null;
        if ($template->background_path && Storage::disk('public')->exists($template->background_path)) {
            $bgData = Storage::disk('public')->get($template->background_path);
            $mime = Storage::disk('public')->mimeType($template->background_path) ?? 'image/jpeg';
            // Convert WEBP to JPEG if needed (GD)
            if ($mime === 'image/webp' && function_exists('imagecreatefromstring')) {
                $im = @imagecreatefromstring($bgData);
                if ($im) {
                    ob_start();
                    imagejpeg($im, null, 85);
                    $bgData = ob_get_clean();
                    imagedestroy($im);
                    $mime = 'image/jpeg';
                }
            }
            $bgBase64 = 'data:' . $mime . ';base64,' . base64_encode($bgData);
        }

        // Signature Image
        $signBase64 = null;
        if ($template->signer_image_path && Storage::disk('public')->exists($template->signer_image_path)) {
            $signData = Storage::disk('public')->get($template->signer_image_path);
            $signBase64 = 'data:image/png;base64,' . base64_encode($signData);
        }

        // QRs
        $verifyUrl = route('certificates.verify', ['no' => $no]);

        // QR Main
        $qrPng = QrCode::format('png')->size($template->qr_size ?? 120)->margin(0)->generate($verifyUrl);
        $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);

        // QR Signature (Small)
        $signQrPng = QrCode::format('png')->size(120)->margin(0)->generate($verifyUrl);
        $signQrBase64 = 'data:image/png;base64,' . base64_encode($signQrPng);

        // 4. Generate PDF
        $pdf = Pdf::loadView('certificates.pdf', [
            'certificate' => null, // Not created yet
            'template' => $template,
            'user' => $row->user,
            'survey' => $row->survey,
            'no' => $no,
            'issuedAt' => $now,
            'signatureDate' => $now,
            'bgBase64' => $bgBase64,
            'signBase64' => $signBase64,
            'qrBase64' => $qrBase64,
            'signQrBase64' => $signQrBase64,
            'qrUrl' => $verifyUrl,
            'preview' => false,
        ])->setPaper($template->paper ?? 'a4', $template->orientation ?? 'landscape');

        $pdfPath = "certificates/pdf/{$y}{$m}-{$row->user_id}-{$row->survey_id}.pdf";
        Storage::put($pdfPath, $pdf->output());

        $hash = hash('sha256', Storage::get($pdfPath));

        Certificate::create([
            'survey_id' => $row->survey_id,
            'user_id' => $row->user_id,
            'certificate_no' => $no,
            'issued_at' => $now,
            'file_path' => $pdfPath,
            'hash' => $hash,
            'certificate_template_id' => $template->id,
        ]);
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
            'index' => Pages\ListSurveyUsers::route('/'),
            'create' => Pages\CreateSurveyUser::route('/create'),
            'edit' => Pages\EditSurveyUser::route('/{record}/edit'),
        ];
    }
}
