<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratUndanganResource\Pages;
use App\Models\KopSurat;
use App\Models\SuratUndangan;
use App\Models\Survey;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SuratUndanganResource extends Resource
{
    protected static ?string $model = SuratUndangan::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Manajemen Dokumen';
    protected static ?string $navigationLabel = 'Surat Undangan';
    protected static ?string $modelLabel = 'Surat Undangan';
    protected static ?string $pluralModelLabel = 'Surat Undangan';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $defaultSigner = SuratUndangan::getDefaultSigner();

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Surat & Kop')
                    ->schema([
                        Forms\Components\Select::make('kop_surat_id')
                            ->label('Kop Surat')
                            ->options(fn () => KopSurat::active()->pluck('name', 'id'))
                            ->default(fn () => KopSurat::getDefaultKop()?->id)
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('survey_id')
                            ->label('Kegiatan / Survei')
                            ->relationship('survey', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->default(fn () => request()->query('survey_id'))
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state) {
                                    $survey = Survey::with('surveyUsers')->find($state);
                                    if ($survey) {
                                        $set('hal', 'Undangan ' . $survey->name);
                                        $set('yth_teks', "Yth. Peserta {$survey->name}\nBPS Kabupaten Demak\ndi\n     tempat");
                                        $dasar = $survey->dasar_surat ?: 'Surat ...';
                                        $suffix = $get('tipe_isi') === 'opsi_1'
                                            ? ', dengan ini kami mengharapkan kehadiran Saudara pada:'
                                            : ', bersama ini disampaikan beberapa hal sebagai berikut:';
                                        $set('paragraf_pembuka', "Menindaklanjuti {$dasar}{$suffix}");
                                        $set('agenda_acara', $survey->name);
                                        $set('peserta_ids', $survey->surveyUsers->pluck('user_id')->toArray());
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('nomor_surat')
                            ->label('Nomor Surat Undangan (Manual)')
                            ->placeholder('Contoh: B-012/33210/VS.220/2026')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Diinput manual karena penomoran surat undangan berbeda dengan surat tugas.'),

                        Forms\Components\Select::make('sifat')
                            ->label('Sifat Surat')
                            ->options([
                                'Biasa'   => 'Biasa',
                                'Segera'  => 'Segera',
                                'Penting' => 'Penting',
                                'Rahasia' => 'Rahasia',
                            ])
                            ->default('Biasa')
                            ->required(),

                        Forms\Components\TextInput::make('lampiran_teks')
                            ->label('Lampiran')
                            ->default('1 (satu) set')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('tanggal_surat')
                            ->label('Tanggal Surat')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('hal')
                            ->label('Hal / Perihal')
                            ->placeholder('Nama kegiatan / perihal undangan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('yth_teks')
                            ->label('Tujuan Surat (Yth.)')
                            ->rows(4)
                            ->default("Yth. Peserta [Nama Kegiatan]\nBPS Kabupaten Demak\ndi\n     tempat")
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Isi Surat Undangan (Template & Word Processor)')
                    ->schema([
                        Forms\Components\Textarea::make('paragraf_pembuka')
                            ->label('Paragraf Pembuka')
                            ->rows(2)
                            ->default('Menindaklanjuti [dasar surat], dengan ini kami mengharapkan kehadiran Saudara pada:')
                            ->helperText('Otomatis terisi saat memilih kegiatan; dapat disesuaikan langsung.')
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('tipe_isi')
                            ->label('Pilihan Format Isi Undangan')
                            ->options([
                                'opsi_1' => 'Opsi 1: Format Agenda / Rapat (Hari, Tanggal, Waktu, Tempat, Acara, Pakaian, Ket.)',
                                'opsi_2' => 'Opsi 2: Format Poin Pembekalan / Arahan (seperti contoh SPKRT)',
                                'custom' => 'Custom Bebas (Edit manual langsung di editor)',
                            ])
                            ->default('opsi_1')
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $pembuka = (string) $get('paragraf_pembuka');
                                if ($state === 'opsi_1' && str_contains($pembuka, 'bersama ini disampaikan')) {
                                    $set('paragraf_pembuka', str_replace(
                                        ', bersama ini disampaikan beberapa hal sebagai berikut:',
                                        ', dengan ini kami mengharapkan kehadiran Saudara pada:',
                                        $pembuka
                                    ));
                                } elseif ($state === 'opsi_2' && str_contains($pembuka, 'mengharapkan kehadiran')) {
                                    $set('paragraf_pembuka', str_replace(
                                        ', dengan ini kami mengharapkan kehadiran Saudara pada:',
                                        ', bersama ini disampaikan beberapa hal sebagai berikut:',
                                        $pembuka
                                    ));
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\Fieldset::make('Generator Isian Opsi 1 (Agenda / Rapat)')
                            ->schema([
                                Forms\Components\TextInput::make('agenda_hari')
                                    ->label('Hari')
                                    ->placeholder('Contoh: Senin - Rabu')
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('agenda_tanggal')
                                    ->label('Tanggal')
                                    ->placeholder('Contoh: 10 - 12 Juni 2026')
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('agenda_waktu')
                                    ->label('Waktu')
                                    ->placeholder('Contoh: Pukul 08.00 s.d. 16.00 WIB')
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('agenda_tempat')
                                    ->label('Tempat')
                                    ->placeholder('Contoh: Aula BPS Kabupaten Demak / Hotel ...')
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('agenda_acara')
                                    ->label('Acara')
                                    ->placeholder('Contoh: Rapat Koordinasi / Pelatihan Petugas')
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('agenda_pakaian')
                                    ->label('Pakaian')
                                    ->placeholder('Contoh: Batik / PSH / Bebas Rapi')
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('agenda_keterangan')
                                    ->label('Keterangan')
                                    ->placeholder('Contoh: Hadir 15 menit sebelum acara dimulai')
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->visible(fn (Get $get) => $get('tipe_isi') === 'opsi_1')
                            ->columnSpanFull(),

                        Forms\Components\Actions::make([
                            Action::make('terapkan_template')
                                ->label('Generate / Terapkan Template ke Editor Surat')
                                ->icon('heroicon-o-sparkles')
                                ->color('primary')
                                ->action(function (Get $get, Set $set) {
                                    $tipe = $get('tipe_isi');
                                    if ($tipe === 'opsi_1') {
                                        $rows = [
                                            'Hari'       => $get('agenda_hari') ?: '-',
                                            'Tanggal'    => $get('agenda_tanggal') ?: '-',
                                            'Waktu'      => $get('agenda_waktu') ?: '-',
                                            'Tempat'     => $get('agenda_tempat') ?: '-',
                                            'Acara'      => $get('agenda_acara') ?: ($get('hal') ?: '-'),
                                            'Pakaian'    => $get('agenda_pakaian') ?: '-',
                                            'Keterangan' => $get('agenda_keterangan') ?: '-',
                                        ];
                                        $html = '<table><tbody>';
                                        foreach ($rows as $label => $val) {
                                            if ($val !== '-') {
                                                $html .= "<tr><td style=\"width: 110px;\">{$label}</td><td style=\"width: 15px;\">:</td><td>" . e($val) . "</td></tr>";
                                            }
                                        }
                                        $html .= '</tbody></table>';
                                        $set('isi_surat_html', $html);
                                    } elseif ($tipe === 'opsi_2') {
                                        $kegiatan = e($get('hal') ?: 'Kegiatan');
                                        $html = "<ol>"
                                            . "<li>Seluruh peserta wajib mengikuti rangkaian {$kegiatan} sesuai jadwal terlampir (Lampiran 2).</li>"
                                            . "<li>Peserta yang ditetapkan (Lampiran 1) wajib melakukan registrasi dan konfirmasi kehadiran sebelum pelaksanaan.</li>"
                                            . "<li>Ketentuan pakaian selama kegiatan berlangsung adalah pakaian rapi/batik/PSH sesuai hari pelaksanaan.</li>"
                                            . "<li>Materi dan tautan koordinasi dapat diakses melalui grup WhatsApp resmi kegiatan.</li>"
                                            . "</ol>";
                                        $set('isi_surat_html', $html);
                                    }
                                }),
                        ])
                            ->visible(fn (Get $get) => in_array($get('tipe_isi'), ['opsi_1', 'opsi_2']))
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('isi_surat_html')
                            ->label('Editor Isi Surat (Dapat Diedit Bebas)')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'bulletList',
                                'orderedList',
                                'undo',
                                'redo',
                            ])
                            ->helperText('Hasil generate template akan masuk ke sini dan masih bisa disunting bebas (bold, italic, list, undo/redo).')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Lampiran 1: Daftar Peserta')
                    ->schema([
                        Forms\Components\Toggle::make('sertakan_lampiran_peserta')
                            ->label('Sertakan Lampiran 1 (Daftar Peserta)')
                            ->default(true)
                            ->live(),

                        Forms\Components\Select::make('peserta_ids')
                            ->label('Pilih Peserta yang Dimasukkan ke Lampiran 1')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Get $get) {
                                $surveyId = $get('survey_id');
                                if ($surveyId) {
                                    $survey = Survey::with(['surveyUsers.user.profile', 'surveyUsers.class'])->find($surveyId);
                                    if ($survey) {
                                        return $survey->surveyUsers->mapWithKeys(function ($su) use ($survey) {
                                            $name = $su->user?->profile?->full_name ?: ($su->user?->name ?: 'Peserta');
                                            $role = $su->role ?: 'Peserta';
                                            if ($survey->is_multiclass) {
                                                $kelas = $su->class?->name ?: 'Tanpa Kelas';
                                                return [$su->user_id => "[{$kelas}] {$name} ({$role})"];
                                            }
                                            return [$su->user_id => "{$name} ({$role})"];
                                        });
                                    }
                                }

                                return User::with('profile')->get()->mapWithKeys(function ($u) {
                                    return [$u->id => $u->profile?->full_name ?: $u->name];
                                });
                            })
                            ->helperText('Kosongkan untuk menampilkan seluruh peserta kegiatan terpilih, atau pilih sebagian peserta saja.')
                            ->visible(fn (Get $get) => (bool) $get('sertakan_lampiran_peserta')),

                        Forms\Components\CheckboxList::make('kolom_lampiran_peserta')
                            ->label('Kolom Tambahan Lampiran 1')
                            ->options([
                                'nip'            => 'NIP',
                                'asal_kecamatan' => 'Asal Kecamatan / Alamat',
                                'sobat_id'       => 'Sobat ID',
                                'email'          => 'Email',
                                'no_hp'          => 'Nomor HP / Kontak',
                            ])
                            ->default(['nip', 'asal_kecamatan', 'sobat_id', 'email'])
                            ->columns(3)
                            ->helperText('Kolom Wajib (No, Nama Peserta, dan Bertugas Sebagai / Jabatan) selalu ditampilkan otomatis.')
                            ->visible(fn (Get $get) => (bool) $get('sertakan_lampiran_peserta')),
                    ]),

                Forms\Components\Section::make('Lampiran 2: Jadwal Kegiatan')
                    ->schema([
                        Forms\Components\Toggle::make('sertakan_lampiran_jadwal')
                            ->label('Sertakan Lampiran 2 (Jadwal Kegiatan)')
                            ->default(true)
                            ->live(),

                        Forms\Components\Radio::make('mode_jadwal')
                            ->label('Metode Penyusunan Jadwal')
                            ->options([
                                'builder' => 'Buat Jadwal Interaktif (Schedule Builder)',
                                'upload'  => 'Upload Gambar Jadwal (JPG / PNG)',
                            ])
                            ->default('builder')
                            ->inline()
                            ->live()
                            ->visible(fn (Get $get) => (bool) $get('sertakan_lampiran_jadwal')),

                        Forms\Components\FileUpload::make('jadwal_image_path')
                            ->label('Upload File Gambar Jadwal')
                            ->image()
                            ->disk('public')
                            ->directory('surat-undangan/jadwal')
                            ->helperText('Gambar akan ditempel proporsional di bawah judul Lampiran 2.')
                            ->visible(fn (Get $get) => (bool) $get('sertakan_lampiran_jadwal') && $get('mode_jadwal') === 'upload'),

                        Forms\Components\Toggle::make('tampilkan_durasi_jadwal')
                            ->label('Tampilkan Kolom Durasi Otomatis (contoh: 0:30)')
                            ->default(true)
                            ->visible(fn (Get $get) => (bool) $get('sertakan_lampiran_jadwal') && $get('mode_jadwal') === 'builder'),

                        Forms\Components\Repeater::make('schedules')
                            ->label('Rincian Jadwal Kegiatan')
                            ->relationship('schedules')
                            ->schema([
                                Forms\Components\TextInput::make('hari')
                                    ->label('Hari')
                                    ->placeholder('Senin')
                                    ->maxLength(50),
                                Forms\Components\DatePicker::make('tanggal')
                                    ->label('Tanggal')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $set('hari', Carbon::parse($state)->locale('id')->translatedFormat('l'));
                                        }
                                    }),
                                Forms\Components\TextInput::make('waktu_mulai')
                                    ->label('Mulai')
                                    ->placeholder('08.00')
                                    ->required()
                                    ->maxLength(10),
                                Forms\Components\TextInput::make('waktu_selesai')
                                    ->label('Selesai')
                                    ->placeholder('08.30')
                                    ->required()
                                    ->maxLength(10),
                                Forms\Components\TextInput::make('materi')
                                    ->label('Materi / Aktivitas')
                                    ->required()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('jp')
                                    ->label('JP')
                                    ->numeric()
                                    ->minValue(0),
                                Forms\Components\Select::make('metode')
                                    ->label('Metode')
                                    ->options([
                                        'Synchronous'  => 'Synchronous',
                                        'Asynchronous' => 'Asynchronous',
                                        'Pleno'        => 'Pleno',
                                        'Kelas'        => 'Kelas',
                                        'Mandiri'      => 'Mandiri',
                                    ]),
                                Forms\Components\TextInput::make('keterangan')
                                    ->label('Keterangan / Narasumber')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ])
                            ->columns(5)
                            ->orderColumn('urutan')
                            ->defaultItems(0)
                            ->addActionLabel('+ Tambah Baris Jadwal')
                            ->visible(fn (Get $get) => (bool) $get('sertakan_lampiran_jadwal') && $get('mode_jadwal') === 'builder'),
                    ]),

                Forms\Components\Section::make('Penandatangan (Blok Tanda Tangan)')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('signer_name')
                            ->label('Nama Penandatangan')
                            ->default($defaultSigner['name'])
                            ->required(),
                        Forms\Components\TextInput::make('signer_title')
                            ->label('Jabatan Penandatangan')
                            ->default($defaultSigner['title'])
                            ->required(),
                        Forms\Components\TextInput::make('signer_city')
                            ->label('Kota')
                            ->default($defaultSigner['city'])
                            ->required(),
                        Forms\Components\TextInput::make('signer_nip')
                            ->label('NIP')
                            ->default($defaultSigner['nip']),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_surat')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('hal')
                    ->label('Hal / Kegiatan')
                    ->searchable()
                    ->wrap()
                    ->limit(45),

                Tables\Columns\TextColumn::make('survey.name')
                    ->label('Kegiatan')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('kopSurat.name')
                    ->label('Kop Surat')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('tanggal_surat')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('sertakan_lampiran_peserta')
                    ->label('Lamp. Peserta')
                    ->boolean(),

                Tables\Columns\IconColumn::make('sertakan_lampiran_jadwal')
                    ->label('Lamp. Jadwal')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('preview_pdf')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (SuratUndangan $record) => 'Preview: ' . $record->nomor_surat)
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (SuratUndangan $record) => new \Illuminate\Support\HtmlString(
                        '<iframe src="' . route('surat-undangan.preview-pdf', $record) . '" style="width: 100%; height: 78vh; border: none;"></iframe>'
                    )),

                Tables\Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->url(fn (SuratUndangan $record) => route('surat-undangan.download-pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('download_docx')
                    ->label('Word')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->url(fn (SuratUndangan $record) => route('surat-undangan.download-docx', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuratUndangans::route('/'),
            'create' => Pages\CreateSuratUndangan::route('/create'),
            'edit'   => Pages\EditSuratUndangan::route('/{record}/edit'),
        ];
    }
}
