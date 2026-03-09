<?php

namespace App\Filament\Resources\SuratTugasResource\Pages;

use App\Filament\Resources\SuratTugasResource;
use App\Models\BlockedSuratTugasNumber;
use App\Models\SuratTugas;
use App\Settings\SystemSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ManageBlockedNumbers extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = SuratTugasResource::class;
    protected static string $view = 'filament.resources.surat-tugas-resource.pages.manage-blocked-numbers';
    protected static ?string $title = 'Kelola Nomor Terblokir';

    public ?int $year = null;
    public ?int $nomor_dari = null;
    public ?int $nomor_sampai = null;
    public ?string $keterangan = null;

    public function mount(): void
    {
        $this->year = now()->year;
        $nextNumber = SuratTugas::getNextNomorUrut($this->year);
        $this->nomor_dari = $nextNumber;
        $this->nomor_sampai = $nextNumber;
    }

    protected function getForms(): array
    {
        return [
            'blockingForm',
        ];
    }

    public function blockingForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Block Nomor Baru')
                    ->description('Blokir range nomor urut agar tidak dipakai saat pembuatan surat tugas.')
                    ->schema([
                        Forms\Components\TextInput::make('year')
                            ->label('Tahun')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $nextNumber = SuratTugas::getNextNomorUrut((int) $state);
                                    $set('nomor_dari', $nextNumber);
                                    $set('nomor_sampai', $nextNumber);
                                }
                            }),

                        Forms\Components\TextInput::make('nomor_dari')
                            ->label('Nomor Dari')
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Forms\Components\TextInput::make('nomor_sampai')
                            ->label('Nomor Sampai')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText(function (Forms\Get $get) {
                                $dari = (int) ($get('nomor_dari') ?? 0);
                                $sampai = (int) ($get('nomor_sampai') ?? 0);
                                if ($dari > 0 && $sampai > 0 && $sampai >= $dari) {
                                    $count = $sampai - $dari + 1;
                                    return "{$count} nomor akan di-block (#{$dari} s/d #{$sampai})";
                                }
                                return '';
                            }),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan (Opsional)')
                            ->placeholder('Contoh: Reserved untuk ST Sakernas batch 2')
                            ->maxLength(500),
                    ])->columns(4),
            ]);
    }

    public function blockNumbers(): void
    {
        $dari = (int) $this->nomor_dari;
        $sampai = (int) $this->nomor_sampai;
        $year = (int) $this->year;
        $keterangan = $this->keterangan;

        if (!$dari || !$sampai || !$year) {
            Notification::make()
                ->title('Lengkapi semua field yang diperlukan')
                ->danger()
                ->send();
            return;
        }

        if ($sampai < $dari) {
            Notification::make()
                ->title('Nomor "Sampai" harus >= Nomor "Dari"')
                ->danger()
                ->send();
            return;
        }

        if (($sampai - $dari + 1) > 100) {
            Notification::make()
                ->title('Maksimal 100 nomor dalam sekali block')
                ->danger()
                ->send();
            return;
        }

        $count = BlockedSuratTugasNumber::blockRange($dari, $sampai, $year, $keterangan);

        if ($count > 0) {
            Notification::make()
                ->title("Berhasil memblokir {$count} nomor")
                ->body("Nomor #{$dari} s/d #{$sampai} untuk tahun {$year}")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Tidak ada nomor baru yang di-block')
                ->body('Semua nomor dalam range sudah dipakai atau sudah di-block sebelumnya.')
                ->warning()
                ->send();
        }

        // Reset the form with next available number
        $nextNumber = SuratTugas::getNextNomorUrut($year);
        $this->nomor_dari = $nextNumber;
        $this->nomor_sampai = $nextNumber;
        $this->keterangan = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BlockedSuratTugasNumber::query()->orderBy('year', 'desc')->orderBy('nomor_urut', 'asc'))
            ->columns([
                Tables\Columns\TextColumn::make('nomor_urut')
                    ->label('Nomor Urut')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('year')
                    ->label('Tahun')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nomor_surat_preview')
                    ->label('Preview Nomor Surat')
                    ->getStateUsing(function (BlockedSuratTugasNumber $record) {
                        $settings = app(SystemSettings::class);
                        $prefix = $settings->surat_prefix ?? 'B';
                        $office = $settings->office_code ?? '33210';
                        $urut = str_pad($record->nomor_urut, 4, '0', STR_PAD_LEFT);
                        return "{$prefix}-{$urut}/{$office}/KP.650/{$record->year}";
                    }),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(50)
                    ->tooltip(fn(BlockedSuratTugasNumber $record) => $record->keterangan),
                Tables\Columns\TextColumn::make('blocker.name')
                    ->label('Di-block oleh'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Block')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(function () {
                        $years = BlockedSuratTugasNumber::distinct()
                            ->pluck('year')
                            ->sort()
                            ->reverse()
                            ->mapWithKeys(fn($y) => [$y => (string) $y])
                            ->toArray();
                        if (empty($years)) {
                            $years = [now()->year => (string) now()->year];
                        }
                        return $years;
                    })
                    ->default(now()->year),
            ])
            ->actions([
                Tables\Actions\Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Release Nomor?')
                    ->modalDescription(fn(BlockedSuratTugasNumber $record) => "Nomor #{$record->nomor_urut} tahun {$record->year} akan di-release dan bisa dipakai untuk surat tugas baru.")
                    ->action(function (BlockedSuratTugasNumber $record) {
                        $record->delete();
                        Notification::make()
                            ->title("Nomor #{$record->nomor_urut} berhasil di-release")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('releaseBulk')
                        ->label('Release Selected')
                        ->icon('heroicon-o-lock-open')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = $records->count();
                            $records->each->delete();

                            Notification::make()
                                ->title("{$count} nomor berhasil di-release")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('nomor_urut', 'asc');
    }
}
