<?php

namespace Tests\Feature;

use App\Filament\Resources\SuratUndanganResource\Pages\CreateSuratUndangan;
use App\Filament\Resources\SurveyResource\Pages\CreateSurvey;
use App\Models\KopSurat;
use App\Models\SuratUndangan;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\SuratUndanganDocxService;
use App\Services\SuratUndanganPdfService;
use App\Settings\SystemSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuratUndanganImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Organik', 'guard_name' => 'web']);
        SystemSettings::fake(SystemSettings::defaults());
        Gate::before(fn () => true);

        $this->admin = User::factory()->create(['name' => 'Admin']);
        $this->admin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
    }

    public function test_peserta_sorting_by_custom_role_order_name_and_class_and_asal_options(): void
    {
        $survey = Survey::create([
            'name' => 'Pelatihan Sensus',
            'dasar_surat' => 'DIPA 2026',
            'rujukan_surat' => 'Surat Kepala BPS Provinsi Nomor B-999/2026',
            'is_pelatihan' => true,
            'is_active' => true,
        ]);

        $kop = KopSurat::create([
            'name' => 'Kop Default',
            'is_default' => true,
            'is_active' => true,
        ]);

        $createParticipant = function (string $name, string $role, string $kelas, string $asalPeserta, string $asalProfile) use ($survey): User {
            $user = User::factory()->create(['name' => $name]);
            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $name,
                    'address' => $asalProfile,
                ]
            );
            SurveyUser::create([
                'survey_id' => $survey->id,
                'user_id' => $user->id,
                'role' => $role,
                'role_pelatihan' => $role,
                'kelas' => $kelas,
                'asal' => $asalPeserta,
                'status' => 'registered',
            ]);

            return $user;
        };

        // Intentionally insert out of order
        $createParticipant('Zainal', 'Panitia', 'Kelas B', 'Demak Kota', 'Kec. Demak');
        $createParticipant('Budi', 'PPL', 'Kelas A', 'Sayung', 'Kec. Sayung');
        $createParticipant('Ani', 'PPL', 'Kelas A', 'Mranggen', 'Kec. Mranggen');
        $createParticipant('Citra', 'PML', 'Kelas A', 'Guntur', 'Kec. Guntur');
        $createParticipant('Dedi', 'Inda', 'Kelas A', 'Dempet', 'Kec. Dempet');

        $undangan = SuratUndangan::create([
            'survey_id' => $survey->id,
            'kop_surat_id' => $kop->id,
            'nomor_surat' => 'B-001/33210/2026',
            'sifat' => 'Biasa',
            'lampiran_teks' => '1 (satu) set',
            'hal' => 'Undangan Pelatihan Sensus',
            'tanggal_surat' => '2026-06-10',
            'urutan_role_peserta' => ['PPL', 'PML', 'Inda', 'Panitia'],
            'kolom_lampiran_peserta' => ['asal_peserta', 'asal_kecamatan'],
            'sertakan_lampiran_peserta' => true,
            'sertakan_lampiran_jadwal' => false,
        ]);

        $pdfService = app(SuratUndanganPdfService::class);
        $grouped = $pdfService->resolvePesertaData($undangan);

        // Classes sorted naturally: Kelas A before Kelas B
        $this->assertSame(['Kelas A', 'Kelas B'], array_keys($grouped));

        // Within Kelas A: PPL (Ani, Budi), PML (Citra), Inda (Dedi)
        $kelasANames = array_column($grouped['Kelas A'], 'nama');
        $this->assertSame(['Ani', 'Budi', 'Citra', 'Dedi'], $kelasANames);

        // Both asal_peserta and asal_kecamatan are populated from their respective sources
        $this->assertSame('Mranggen', $grouped['Kelas A'][0]['asal_peserta']);
        $this->assertSame('Kec. Mranggen', $grouped['Kelas A'][0]['asal_kecamatan']);

        // PDF & DOCX generation succeed
        $pdf = $pdfService->generate($undangan);
        $this->assertNotEmpty($pdf->output());

        $docxService = app(SuratUndanganDocxService::class);
        $phpWord = $docxService->generate($undangan);
        $this->assertNotNull($phpWord);
    }

    public function test_create_surat_undangan_uses_rujukan_surat_for_paragraf_pembuka(): void
    {
        $survey = Survey::create([
            'name' => 'Pelatihan Ekonomi',
            'dasar_surat' => 'DIPA BPS Demak 2026',
            'rujukan_surat' => 'Surat Kepala BPS Jateng Nomor B-555/2026',
            'is_pelatihan' => true,
            'is_active' => true,
        ]);

        Livewire::withQueryParams(['survey_id' => $survey->id])
            ->test(CreateSuratUndangan::class)
            ->assertFormSet([
                'paragraf_pembuka' => 'Menindaklanjuti Surat Kepala BPS Jateng Nomor B-555/2026, dengan ini kami mengharapkan kehadiran Saudara pada:',
            ]);
    }

    public function test_multiclass_toggle_is_only_visible_for_pelatihan_in_survey_resource(): void
    {
        Livewire::test(CreateSurvey::class)
            ->fillForm(['jenis_kegiatan' => 'non-pelatihan'])
            ->assertFormFieldIsHidden('is_multiclass')
            ->fillForm(['jenis_kegiatan' => 'pelatihan'])
            ->assertFormFieldIsVisible('is_multiclass');
    }
}
