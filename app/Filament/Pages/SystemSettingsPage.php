<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Get;
use Filament\Pages\Page;

use App\Settings\SystemSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Auth;

class SystemSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;
    protected static ?string $slug = 'system-settings';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $title = 'Pengaturan Sistem';
    protected static string $view = 'filament.pages.system-settings-page';
    protected static ?int $navigationSort = 70;

    public ?array $data = [];

    public function mount(SystemSettings $s): void
    {
        $this->form->fill([
            'default_office_name' => $s->default_office_name ?? 'BPS Kabupaten Demak',
            'default_office_lat' => $s->default_office_lat ?? 0,
            'default_office_lng' => $s->default_office_lng ?? 0,
            'default_geofence_radius_m' => $s->default_geofence_radius_m ?? 100,
            'default_work_start' => $s->default_work_start ?? '08:00',
            'default_work_end' => $s->default_work_end ?? '16:00',
            'default_workdays' => $s->default_workdays ?? [1, 2, 3, 4, 5],
            'cert_city' => $s->cert_city ?? 'Demak',
            'cert_signer_name' => $s->cert_signer_name ?? '-',
            'cert_signer_nip' => $s->cert_signer_nip ?? '-',
            'cert_signer_title' => $s->cert_signer_title ?? 'Kepala Badan Pusat Statistik Kabupaten Demak',
            'cert_signer_signature_path' => $s->cert_signer_signature_path,
            'office_code' => $s->office_code ?? '33210',
            'surat_prefix' => $s->surat_prefix ?? 'B',
            'surat_tugas_template_path' => $s->surat_tugas_template_path,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Format Nomor Surat')
                ->description('Pastikan format sesuai: {Prefix}-{Urut}/{KodeKantor}/{Klasifikasi}/{Tahun}')
                ->schema([
                    TextInput::make('office_code')->label('Kode Kantor')->required()->maxLength(20),
                    TextInput::make('surat_prefix')->label('Prefix Surat')->required()->maxLength(10),
                ])->columns(2),
            Group::make()->schema([ // ====== KOLOM KIRI
                Section::make('Lokasi Kantor Default')->schema([
                    TextInput::make('default_office_name')
                        ->label('Nama Kantor')->required()->maxLength(100),

                    Map::make('default_location')
                        ->label('Location')
                        ->columnSpanFull()
                        ->default(fn() => [
                            'lat' => $this->data['default_office_lat'] ?? -6.894561,
                            'lng' => $this->data['default_office_lng'] ?? 110.637492,
                        ])
                        ->afterStateUpdated(function (Set $set, ?array $state): void {
                            if (!$state)
                                return;
                            $set('default_office_lat', $state['lat']);
                            $set('default_office_lng', $state['lng']);
                        })
                        ->afterStateHydrated(function ($state, $record, Set $set, Get $get): void {
                            $lat = $get('default_office_lat');
                            $lng = $get('default_office_lng');
                            if ($lat !== null && $lng !== null) {
                                $set('default_location', ['lat' => (float) $lat, 'lng' => (float) $lng]);
                            }
                        })
                        ->liveLocation()
                        ->showMarker()
                        ->markerColor('#22c55e')
                        ->showFullscreenControl()
                        ->showZoomControl()
                        ->draggable()
                        // === Satellite (Esri World Imagery) ===
                        ->tilesUrl("http://mt0.google.com/vt/lyrs=y&hl=en&x={x}&y={y}&z={z}&s=Ga")
                        ->zoom(16)
                        ->detectRetina(),

                    Group::make()->schema([
                        TextInput::make('default_office_lat')->label('Latitude')->required()->numeric(),
                        TextInput::make('default_office_lng')->label('Longitude')->required()->numeric(),
                    ])->columns(2),

                    TextInput::make('default_geofence_radius_m')
                        ->label('Radius (m)')
                        ->numeric()->minValue(10)->required()->suffix('m'),
                ]),


            ])->columns(1),

            Group::make()->schema([ // ====== KOLOM KANAN
                Section::make('Jam & Hari Kerja Default')->schema([
                    TimePicker::make('default_work_start')->label('Mulai')->seconds(false)->required(),
                    TimePicker::make('default_work_end')->label('Selesai')->seconds(false)->required(),
                    CheckboxList::make('default_workdays')->label('Hari Kerja')->columns(4)->required()
                        ->options([
                            1 => 'Senin',
                            2 => 'Selasa',
                            3 => 'Rabu',
                            4 => 'Kamis',
                            5 => 'Jumat',
                            6 => 'Sabtu',
                            7 => 'Minggu',
                        ]),
                ])->columns(1),

                Section::make('Pejabat Penandatangan (Kepala)')->schema([
                    TextInput::make('cert_city')->label('Kota Penetapan')->required(),
                    TextInput::make('cert_signer_name')->label('Nama Pejabat')->required(),
                    TextInput::make('cert_signer_nip')->label('NIP')->required(),
                    TextInput::make('cert_signer_title')->label('Jabatan')->required(),
                    FileUpload::make('cert_signer_signature_path')
                        ->label('Scan Tanda Tangan')
                        ->image()
                        ->directory('signatures')
                        ->visibility('public')
                        ->maxSize(2048),


                    Section::make('Template Surat Tugas (.docx)')
                        ->description('Upload file .docx dengan variabel: ${nomor_surat}, ${nama_pegawai}, ${nip_pegawai}, ${jabatan_pegawai}, ${jabatan_tugas}, ${tanggal_surat}, ${nama_kepala}, ${nip_kepala}')
                        ->schema([
                            FileUpload::make('surat_tugas_template_path')
                                ->label('File Template')
                                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                ->directory('templates')
                                ->visibility('public')
                                ->maxSize(5120) // 5MB
                                ->downloadable(),
                        ])->columns(1),

                ])->columns(1), // End Section Pejabat

            ])->columns(1),

        ])
            ->columns(2)   // <— dua kolom: kiri & kanan
            ->statePath('data');
    }

    public function save(): void
    {
        $state = collect($this->form->getState())
            ->except('default_location') // ini field bantu MapPicker
            ->toArray();

        if (strcmp($state['default_work_start'], $state['default_work_end']) >= 0) {
            Notification::make()->title('Jam mulai harus < jam selesai')->danger()->send();
            return;
        }

        app(SystemSettings::class)->fill($state)->save();

        Notification::make()->title('Pengaturan tersimpan')->success()->send();
    }


}
