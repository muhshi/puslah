<?php

namespace App\Filament\Widgets;

use App\Models\SuratTugas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestSuratTugasWidget extends BaseWidget
{
    protected static ?string $heading = 'Surat Tugas Terakhir';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(SuratTugas::query()->with('user')->latest()->limit(5))
            ->columns([
                TextColumn::make('nomor_surat')->label('Nomor')->sortable(),
                TextColumn::make('user.name')->label('Pegawai'),
                TextColumn::make('keperluan')->limit(50),
                TextColumn::make('tanggal')->date()->label('Tanggal'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->paginated(false);
    }
}
