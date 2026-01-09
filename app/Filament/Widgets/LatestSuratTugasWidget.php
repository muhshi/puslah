<?php

namespace App\Filament\Widgets;

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
            ->query(\App\Models\SuratTugas::query()->latest()->limit(5))
            ->columns([
                Tables\Columns\TextColumn::make('nomor_surat')->label('Nomor')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Pegawai'),
                Tables\Columns\TextColumn::make('keperluan')->limit(50),
                Tables\Columns\TextColumn::make('tanggal')->date()->label('Tanggal'),
                Tables\Columns\TextColumn::make('status')
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
