<?php

namespace App\Filament\Pages;

use App\Exports\PelatihanLkPenilaianTemplateExport;
use App\Exports\PelatihanPenilaianPlatformExport;
use App\Imports\PelatihanLkPenilaianImport;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Services\PelatihanPenilaianService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class LembarKerjaPenilaian extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Kegiatan';

    protected static ?string $navigationLabel = 'LK Penilaian Peserta';

    protected static ?string $title = 'Lembar Kerja Penilaian Peserta Pelatihan';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.lembar-kerja-penilaian';

    public ?int $surveyId = null;

    public ?string $kelasFilter = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Editable component scores keyed by survey_user_id => [comp_key => value].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $scoresInput = [];

    public function mount(): void
    {
        $requestedSurveyId = request()->query('survey_id');
        if ($requestedSurveyId) {
            $this->surveyId = (int) $requestedSurveyId;
        } else {
            $this->surveyId = Survey::query()
                ->pelatihan()
                ->orderByDesc('is_active')
                ->orderByDesc('created_at')
                ->value('id');
        }

        $this->form->fill([
            'surveyId' => $this->surveyId,
            'kelasFilter' => $this->kelasFilter,
        ]);

        $this->loadScoresInput();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['data.surveyId', 'surveyId'], true)) {
            $this->surveyId = filled($this->data['surveyId'] ?? null) ? (int) $this->data['surveyId'] : null;
            $this->kelasFilter = null;
            $this->data['kelasFilter'] = null;
            $this->loadScoresInput();
        } elseif (in_array($property, ['data.kelasFilter', 'kelasFilter'], true)) {
            $this->kelasFilter = filled($this->data['kelasFilter'] ?? null) ? (string) $this->data['kelasFilter'] : null;
            $this->loadScoresInput();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Pelatihan & Kelas')
                    ->schema([
                        Select::make('surveyId')
                            ->label('Pilih Pelatihan')
                            ->options(fn () => Survey::query()
                                ->pelatihan()
                                ->orderByDesc('is_active')
                                ->orderByDesc('created_at')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->live(),

                        Select::make('kelasFilter')
                            ->label('Filter Kelas')
                            ->placeholder('Semua Kelas')
                            ->options(function (): array {
                                $survey = $this->getSelectedSurvey();
                                if (! $survey) {
                                    return [];
                                }

                                $classes = $survey->surveyUsers()
                                    ->pluck('kelas')
                                    ->map(fn (?string $k) => filled($k) ? trim($k) : 'Kelas A')
                                    ->unique()
                                    ->values()
                                    ->all();
                                usort($classes, 'strnatcasecmp');

                                return array_combine($classes, $classes) ?: [];
                            })
                            ->live(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function getSelectedSurvey(): ?Survey
    {
        if (! $this->surveyId) {
            return null;
        }

        return Survey::find($this->surveyId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDataset(): array
    {
        $survey = $this->getSelectedSurvey();
        if (! $survey) {
            return [
                'weights' => Survey::DEFAULT_PENILAIAN_WEIGHTS,
                'classes' => [],
                'class_officials' => [],
                'rows' => [],
            ];
        }

        return app(PelatihanPenilaianService::class)->build($survey, $this->kelasFilter);
    }

    public function loadScoresInput(): void
    {
        $this->scoresInput = [];
        $dataset = $this->getDataset();
        foreach ($dataset['rows'] as $row) {
            $suId = (int) $row['survey_user_id'];
            $entry = [];
            foreach ($dataset['weights'] as $comp) {
                $val = $row['nilai_komponen'][$comp['key']] ?? null;
                $entry[$comp['key']] = $val !== null && $val !== '' ? $val : '';
            }
            $this->scoresInput[$suId] = $entry;
        }
    }

    public function saveRowScore(int $surveyUserId): void
    {
        $survey = $this->getSelectedSurvey();
        if (! $survey) {
            return;
        }

        $su = SurveyUser::where('survey_id', $survey->id)->find($surveyUserId);
        if (! $su) {
            return;
        }

        $service = app(PelatihanPenilaianService::class);
        $weights = $service->getWeights($survey);
        $input = $this->scoresInput[$surveyUserId] ?? [];

        $cleaned = [];
        foreach ($weights as $comp) {
            $raw = $input[$comp['key']] ?? null;
            if ($raw !== null && $raw !== '' && is_numeric($raw)) {
                $cleaned[$comp['key']] = max(0, min(100, round((float) $raw, 2)));
            }
        }

        $finalScore = $service->calculateFinalScore($cleaned, $weights);

        $su->update([
            'nilai_komponen' => $cleaned,
            'score' => $finalScore !== null ? (int) round($finalScore) : null,
            'is_lulus' => true,
        ]);

        Notification::make()
            ->title('Nilai disimpan')
            ->success()
            ->duration(1500)
            ->send();
    }

    public function saveAllScores(): void
    {
        $survey = $this->getSelectedSurvey();
        if (! $survey) {
            return;
        }

        $service = app(PelatihanPenilaianService::class);
        $weights = $service->getWeights($survey);
        $updated = 0;

        foreach ($this->scoresInput as $suId => $input) {
            $su = SurveyUser::where('survey_id', $survey->id)->find((int) $suId);
            if (! $su) {
                continue;
            }

            $cleaned = [];
            foreach ($weights as $comp) {
                $raw = $input[$comp['key']] ?? null;
                if ($raw !== null && $raw !== '' && is_numeric($raw)) {
                    $cleaned[$comp['key']] = max(0, min(100, round((float) $raw, 2)));
                }
            }

            $finalScore = $service->calculateFinalScore($cleaned, $weights);
            $su->update([
                'nilai_komponen' => $cleaned,
                'score' => $finalScore !== null ? (int) round($finalScore) : $su->score,
                'is_lulus' => true,
            ]);
            $updated++;
        }

        Notification::make()
            ->title("Berhasil menyimpan nilai {$updated} peserta")
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aturBobot')
                ->label('Atur Komponen & Bobot')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => $this->getSelectedSurvey() !== null)
                ->fillForm(function (): array {
                    $survey = $this->getSelectedSurvey();
                    $weights = $survey ? app(PelatihanPenilaianService::class)->getWeights($survey) : Survey::DEFAULT_PENILAIAN_WEIGHTS;

                    return ['weights' => $weights];
                })
                ->schema([
                    Repeater::make('weights')
                        ->label('Komponen Penilaian (Total Bobot Harus 100%)')
                        ->schema([
                            TextInput::make('label')
                                ->label('Nama Komponen')
                                ->placeholder('Contoh: Pre Test / Post Test / Pendalaman')
                                ->required(),
                            TextInput::make('weight')
                                ->label('Bobot (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Tambah Komponen')
                        ->minItems(1),
                ])
                ->action(function (array $data): void {
                    $survey = $this->getSelectedSurvey();
                    if (! $survey) {
                        return;
                    }

                    $items = $data['weights'] ?? [];
                    $totalWeight = collect($items)->sum(fn (array $item) => (float) ($item['weight'] ?? 0));

                    if (abs($totalWeight - 100.0) > 0.01) {
                        Notification::make()
                            ->title("Total bobot harus 100% (saat ini: {$totalWeight}%)")
                            ->danger()
                            ->send();

                        return;
                    }

                    $normalized = [];
                    foreach ($items as $item) {
                        $label = trim((string) ($item['label'] ?? ''));
                        if ($label === '') {
                            continue;
                        }
                        $normalized[] = [
                            'key' => Str::slug($label, '_'),
                            'label' => $label,
                            'weight' => (float) $item['weight'],
                            'required' => in_array(Str::slug($label, '_'), ['pre_test', 'post_test'], true),
                        ];
                    }

                    $survey->update(['penilaian_weights' => $normalized]);
                    $this->loadScoresInput();

                    Notification::make()
                        ->title('Skema bobot penilaian diperbarui')
                        ->success()
                        ->send();
                }),

            Action::make('downloadTemplate')
                ->label('Download Template LK (.xlsx)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => $this->getSelectedSurvey() !== null)
                ->action(function () {
                    $survey = $this->getSelectedSurvey();
                    $slug = Str::slug($survey->name ?: 'Pelatihan', '_');
                    $filename = "LK_Penilaian_Peserta_Pelatihan_{$slug}_Kabupaten_Demak.xlsx";

                    return Excel::download(
                        new PelatihanLkPenilaianTemplateExport($survey, $this->kelasFilter),
                        $filename
                    );
                }),

            Action::make('importNilai')
                ->label('Import Nilai dari LK (.xlsx)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->getSelectedSurvey() !== null)
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel LK Penilaian (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('temp-imports')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $survey = $this->getSelectedSurvey();
                    if (! $survey) {
                        return;
                    }

                    $filePath = Storage::disk('local')->path($data['file']);
                    $importer = new PelatihanLkPenilaianImport($survey);
                    $importer->importFile($filePath);
                    Storage::disk('local')->delete($data['file']);

                    $this->loadScoresInput();

                    $body = "Diperbarui: {$importer->updated} peserta, Dilewati: {$importer->skipped}";
                    if (! empty($importer->errors)) {
                        $body .= ' | '.implode('; ', array_slice($importer->errors, 0, 2));
                    }

                    Notification::make()
                        ->title('Import Nilai Selesai')
                        ->body($body)
                        ->success()
                        ->send();
                }),

            Action::make('exportPlatform')
                ->label('Export Platform Lain (.xlsx)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('warning')
                ->visible(fn (): bool => $this->getSelectedSurvey() !== null)
                ->action(function () {
                    $survey = $this->getSelectedSurvey();
                    $slug = Str::slug($survey->name ?: 'Pelatihan', '_');
                    $filename = "Export_Penilaian_{$slug}.xlsx";

                    return Excel::download(
                        new PelatihanPenilaianPlatformExport($survey, $this->kelasFilter),
                        $filename
                    );
                }),
        ];
    }
}
