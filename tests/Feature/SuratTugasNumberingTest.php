<?php

namespace Tests\Feature;

use App\Models\SuratTugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuratTugasNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::create(['name' => 'Mitra', 'guard_name' => 'web']);
    }

    /** @test */
    public function it_can_detect_skipped_numbers()
    {
        $year = 2026;
        $date = "{$year}-01-01";

        $user = User::factory()->create();
        $survey = \App\Models\Survey::create(['name' => 'Test Survey']);

        // Create surat tugas with gaps: 1, 2, 5
        $this->createSurat($user, $survey, 1, $date);
        $this->createSurat($user, $survey, 2, $date);
        $this->createSurat($user, $survey, 5, $date);

        $skipped = SuratTugas::getSkippedNumbers($year);

        $this->assertEquals([3, 4], $skipped);
        $this->assertEquals("3-4", SuratTugas::formatSkippedNumbers($skipped));
    }

    /** @test */
    public function it_can_detect_multiple_gaps()
    {
        $year = 2026;
        $date = "{$year}-01-01";

        $user = User::factory()->create();
        $survey = \App\Models\Survey::create(['name' => 'Test Survey']);

        // Create: 1, 3, 5, 6, 9
        $numbers = [1, 3, 5, 6, 9];
        foreach ($numbers as $num) {
            $this->createSurat($user, $survey, $num, $date);
        }

        $skipped = SuratTugas::getSkippedNumbers($year);

        $this->assertEquals([2, 4, 7, 8], $skipped);
        $this->assertEquals("2, 4, 7-8", SuratTugas::formatSkippedNumbers($skipped));
    }

    /** @test */
    public function it_calculates_next_number_correctly()
    {
        $year = 2026;
        $date = "{$year}-01-01";
        $user = User::factory()->create();
        $survey = \App\Models\Survey::create(['name' => 'Test Survey']);

        $this->assertEquals(1, SuratTugas::getNextNomorUrut($year));

        $this->createSurat($user, $survey, 5, $date);

        $this->assertEquals(6, SuratTugas::getNextNomorUrut($year));
    }

    private function createSurat($user, $survey, $urut, $date)
    {
        $year = \Carbon\Carbon::parse($date)->year;
        return SuratTugas::create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'nomor_surat' => "B-" . str_pad($urut, 4, '0', STR_PAD_LEFT) . "/33210/KP.650/{$year}",
            'nomor_urut' => $urut,
            'kode_klasifikasi' => 'KP.650',
            'jabatan' => 'Staff',
            'keperluan' => 'Testing',
            'tanggal' => $date,
            'waktu_mulai' => "{$date} 08:00:00",
            'waktu_selesai' => "{$date} 16:00:00",
            'status' => 'pending',
            'signer_name' => 'Test Signer',
            'signer_nip' => '123456789',
            'signer_title' => 'Kepala BPS',
            'signer_city' => 'Jakarta',
            'signer_signature_path' => 'path/to/signature.png',
        ]);
    }
}
