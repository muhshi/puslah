<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

use App\Settings\SystemSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class SystemSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $title = 'Pengaturan Sistem';
    protected static string $view = 'filament.pages.system-settings-page';

    public ?array $data = [];

    public function mount(SystemSettings $s): void
    {
        $this->form->fill([
            'default_office_lat' => $s->default_office_lat ?? 0,
            'default_office_lng' => $s->default_office_lng ?? 0,
            'default_geofence_radius_m' => $s->default_geofence_radius_m ?? 100,
            'default_work_start' => $s->default_work_start ?? '08:00',
            'default_work_end' => $s->default_work_end ?? '16:00',
            'default_workdays' => $s->default_workdays ?? [1, 2, 3, 4, 5],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Fieldset::make('Lokasi Kantor Default')->schema([
                Forms\Components\TextInput::make('default_office_lat')->numeric()->rule('between:-90,90')->required()->label('Latitude'),
                Forms\Components\TextInput::make('default_office_lng')->numeric()->rule('between:-180,180')->required()->label('Longitude'),
                Forms\Components\TextInput::make('default_geofence_radius_m')->numeric()->minValue(10)->suffix('m')->required()->label('Radius'),
            ])->columns(3),

            Forms\Components\Fieldset::make('Jam & Hari Kerja Default')->schema([
                Forms\Components\TimePicker::make('default_work_start')->seconds(false)->required()->label('Mulai'),
                Forms\Components\TimePicker::make('default_work_end')->seconds(false)->required()->label('Selesai'),
                Forms\Components\CheckboxList::make('default_workdays')->options([
                    1 => 'Senin',
                    2 => 'Selasa',
                    3 => 'Rabu',
                    4 => 'Kamis',
                    5 => 'Jumat',
                    6 => 'Sabtu',
                    7 => 'Minggu',
                ])->columns(4)->required()->label('Hari Kerja'),
            ])->columns(3),
        ])->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if (strcmp($state['default_work_start'], $state['default_work_end']) >= 0) {
            Notification::make()->title('Jam mulai harus < jam selesai')->danger()->send();
            return;
        }

        app(SystemSettings::class)->fill($state)->save();

        Notification::make()->title('Pengaturan tersimpan')->success()->send();
    }
}
