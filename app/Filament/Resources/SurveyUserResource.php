<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\BulkAction;
use Throwable;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use App\Models\CertificateTemplate;
use App\Services\CertificateImageService;
use App\Filament\Resources\SurveyUserResource\Pages\ListSurveyUsers;
use App\Filament\Resources\SurveyUserResource\Pages\CreateSurveyUser;
use App\Filament\Resources\SurveyUserResource\Pages\EditSurveyUser;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-m-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Survei';
    protected static ?string $navigationLabel = 'Peserta Survei';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('survey_id')
                ->label('Survei')
                ->options(fn() => Survey::orderBy('name')->pluck('name', 'id'))->searchable()->required(),

            Select::make('user_id')
                ->label('Peserta')
                ->options(fn() => User::orderBy('name')->pluck('name', 'id'))->searchable()->required(),

            Select::make('status')
                ->options(['registered' => 'registered', 'approved' => 'approved'])
                ->default('registered')->required(),

            TextInput::make('score')->numeric()->minValue(0)->maxValue(100)->nullable(),
            Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('survey.name')->label('Survei')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Peserta')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => $state === 'approved' ? 'success' : 'warning'),
                TextColumn::make('score')->numeric()->label('Skor')->sortable(),
                TextColumn::make('registered_at')->date()->label('Terdaftar')->sortable(),
            ])
            ->filters([
                SelectFilter::make('survey_id')->label('Survei')
                    ->options(fn() => Survey::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('status')
                    ->options(['registered' => 'registered', 'approved' => 'approved']),
                Filter::make('registered_at')->schema([
                    DatePicker::make('from'),
                    DatePicker::make('until'),
                ])->query(function ($query, array $data) {
                    return $query
                        ->when($data['from'] ?? null, fn($qq, $d) => $qq->whereDate('registered_at', '>=', $d))
                        ->when($data['until'] ?? null, fn($qq, $d) => $qq->whereDate('registered_at', '<=', $d));
                }),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make()->label('Tambah Peserta'),
            ])
            ->toolbarActions([
                BulkAction::make('approveSelected')
                    ->label('Approve & Terbitkan Sertifikat')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion() // UI rapi setelah selesai
                    ->action(function (Collection $records): void {
                        try {
                            $records->each(function (SurveyUser $row) {
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
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Gagal menerbitkan sebagian/semua sertifikat')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            // log biar tahu akar masalah
                            Log::error('Bulk approve error', ['e' => $e]);
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
                        $records->each(function (SurveyUser $row) use (&$count) {
                            if ($row->status === 'approved') {
                                $row->update(['status' => 'registered']);
                                $count++;
                            }
                        });

                        Notification::make()
                            ->title("Berhasil membatalkan approval {$count} peserta")
                            ->success()
                            ->send();
                    }),
                DeleteBulkAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve + Sertifikat')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn(SurveyUser $r) => $r->status !== 'approved')
                    ->action(function (SurveyUser $r) {
                        try {
                            // Try to issue certificate first
                            $certCreated = self::issueCertificateWithResult($r);

                            if ($certCreated) {
                                $r->update(['status' => 'approved']);
                                Notification::make()
                                    ->title('Berhasil approve & sertifikat diterbitkan')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal: Cek apakah template sertifikat sudah aktif')
                                    ->danger()
                                    ->send();
                            }
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Error saat menerbitkan sertifikat')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            Log::error('Certificate issuance error', ['error' => $e]);
                        }
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

    protected static function issueCertificateWithResult(SurveyUser $row): bool
    {
        // Check if certificate already exists
        if (Certificate::where('survey_id', $row->survey_id)->where('user_id', $row->user_id)->exists()) {
            return true; // Certificate already exists, consider it as success
        }

        // Get Active Template with detailed logging
        $template = CertificateTemplate::where('active', 1)->first();

        // Debug logging
        Log::info('Certificate Template Check', [
            'template_found' => $template ? 'YES' : 'NO',
            'template_id' => $template?->id,
            'template_name' => $template?->name,
            'total_templates' => CertificateTemplate::count(),
            'active_templates' => CertificateTemplate::where('active', 1)->count(),
        ]);

        if (!$template) {
            return false; // No active template
        }

        try {
            self::issueCertificate($row);
            return true;
        } catch (Throwable $e) {
            Log::error('Certificate creation failed', ['error' => $e, 'survey_user_id' => $row->id]);
            return false;
        }
    }


    protected static function issueCertificate(SurveyUser $row): void
    {
        if (Certificate::where('survey_id', $row->survey_id)->where('user_id', $row->user_id)->exists())
            return;

        // 1. Get Active Template
        $template = CertificateTemplate::where('active', 1)->first();
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
        $bgBase64 = CertificateImageService::getBase64Image($template->background_path, 'image/jpeg');
        $signBase64 = CertificateImageService::getBase64Image($template->signer_image_path, 'image/png');

        // QRs
        $verifyUrl = route('certificates.verify', ['no' => $no]);

        // QR Main (using SVG to avoid imagick)
        $qrSvg = QrCode::format('svg')->size($template->qr_size ?? 120)->margin(0)->generate($verifyUrl);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        // QR Signature (Small, using SVG to avoid imagick)
        $signQrSvg = QrCode::format('svg')->size(120)->margin(0)->generate($verifyUrl);
        $signQrBase64 = 'data:image/svg+xml;base64,' . base64_encode($signQrSvg);

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
            'index' => ListSurveyUsers::route('/'),
            'create' => CreateSurveyUser::route('/create'),
            'edit' => EditSurveyUser::route('/{record}/edit'),
        ];
    }
}
