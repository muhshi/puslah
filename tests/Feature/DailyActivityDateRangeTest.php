<?php

namespace Tests\Feature;

use App\Filament\Resources\DailyActivityResource\Pages\CreateDailyActivity;
use App\Models\DailyActivity;
use App\Models\SharedWorkReport;
use App\Models\User;
use App\Telegram\Handlers\DailyActivityTelegramHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyActivityDateRangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->user = User::factory()->create([
            'email' => 'pegawai@bpsdemak.com',
            'name' => 'Pegawai Uji',
        ]);
        $this->actingAs($this->user);
    }

    public function test_it_creates_multiple_daily_activities_from_date_range_skipping_weekends(): void
    {
        $wadah = SharedWorkReport::create([
            'user_id' => $this->user->id,
            'title' => 'Wadah Uji Susenas',
            'is_active' => true,
        ]);

        // Rentang Senin (12 Okt 2026) s.d. Senin (19 Okt 2026) -> 6 hari kerja (lewati Sab-Ming 17-18 Okt)
        Livewire::test(CreateDailyActivity::class)
            ->fillForm([
                'is_range' => true,
                'range_start' => '2026-10-12',
                'range_end' => '2026-10-19',
                'skip_weekends' => true,
                'raw_content' => 'Melakukan pengawasan pencacahan lapangan Susenas',
                'final_content' => 'Melakukan pengawasan pencacahan lapangan Susenas',
                'sharedWorkReports' => [$wadah->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Harus terbentuk 6 catatan kegiatan (Senin-Jumat + Senin berikutnya)
        $activities = DailyActivity::where('user_id', $this->user->id)->orderBy('activity_date')->get();
        $this->assertCount(6, $activities);

        $dates = $activities->pluck('activity_date')->map(fn ($d) => $d->toDateString())->toArray();
        $this->assertEquals([
            '2026-10-12', // Senin
            '2026-10-13', // Selasa
            '2026-10-14', // Rabu
            '2026-10-15', // Kamis
            '2026-10-16', // Jumat
            '2026-10-19', // Senin berikutnya (Sabtu 17 & Minggu 18 dilewati)
        ], $dates);

        // Pastikan relasi wadah tersambung ke setiap hari
        foreach ($activities as $act) {
            $this->assertTrue($act->sharedWorkReports->contains($wadah->id));
        }
    }

    public function test_telegram_handler_detects_date_range(): void
    {
        $handler = app(DailyActivityTelegramHandler::class);

        $range = $handler->resolveActivityDateRange('laporan pengawasan lapangan tgl 13 s.d. 16 oktober 2026');
        $this->assertNotNull($range);
        $this->assertEquals('2026-10-13', $range['start']->toDateString());
        $this->assertEquals('2026-10-16', $range['end']->toDateString());

        // Cross-month test
        $range2 = $handler->resolveActivityDateRange('kegiatan pengolahan data 28 oktober s.d. 2 november 2026');
        $this->assertNotNull($range2);
        $this->assertEquals('2026-10-28', $range2['start']->toDateString());
        $this->assertEquals('2026-11-02', $range2['end']->toDateString());
    }

    public function test_it_creates_single_activity_when_range_is_false(): void
    {
        Livewire::test(CreateDailyActivity::class)
            ->fillForm([
                'is_range' => false,
                'activity_date' => '2026-10-15',
                'raw_content' => 'Rapat koordinasi internal tim IT',
                'final_content' => 'Rapat koordinasi internal tim IT',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = DailyActivity::where('user_id', $this->user->id)->first();
        $this->assertNotNull($activity);
        $this->assertEquals('2026-10-15', $activity->activity_date->toDateString());
        $this->assertEquals('Rapat koordinasi internal tim IT', $activity->final_content);
    }

    public function test_table_has_activity_date_grouping(): void
    {
        $component = Livewire::test(\App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities::class);
        $group = $component->instance()->getTable()->getDefaultGroup();
        $this->assertNotNull($group);
        $this->assertEquals('activity_date', $group->getColumn());
    }
}
