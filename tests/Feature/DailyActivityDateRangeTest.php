<?php

namespace Tests\Feature;

use App\Filament\Resources\DailyActivityResource\Pages\CreateDailyActivity;
use App\Models\DailyActivity;
use App\Models\SharedWorkReport;
use App\Models\User;
use App\Telegram\Handlers\DailyActivityTelegramHandler;
use Carbon\Carbon;
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

    public function test_table_renders_with_records_grouped_by_indonesian_date(): void
    {
        DailyActivity::create([
            'user_id' => $this->user->id,
            'activity_date' => '2026-10-13',
            'raw_content' => 'Uji coba laporan selasa',
            'final_content' => 'Uji coba laporan selasa',
            'status' => 'submitted',
            'source' => 'web',
        ]);

        Livewire::test(\App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities::class)
            ->assertSuccessful()
            ->assertSee('Selasa, 13 Oktober 2026');
    }

    public function test_activities_within_same_date_are_ordered_chronologically_by_created_at(): void
    {
        $date = '2026-10-13';

        // Buat kegiatan 1 (Pagi)
        $act1 = DailyActivity::forceCreate([
            'user_id' => $this->user->id,
            'activity_date' => $date,
            'raw_content' => 'Apel pagi dan briefing',
            'final_content' => 'Apel pagi dan briefing',
            'status' => 'submitted',
            'source' => 'web',
            'created_at' => Carbon::parse('2026-10-13 07:30:00'),
        ]);

        // Buat kegiatan 2 (Siang)
        $act2 = DailyActivity::forceCreate([
            'user_id' => $this->user->id,
            'activity_date' => $date,
            'raw_content' => 'Pengawasan lapangan mitra',
            'final_content' => 'Pengawasan lapangan mitra',
            'status' => 'submitted',
            'source' => 'web',
            'created_at' => Carbon::parse('2026-10-13 13:00:00'),
        ]);

        // Buat kegiatan 3 (Sore)
        $act3 = DailyActivity::forceCreate([
            'user_id' => $this->user->id,
            'activity_date' => $date,
            'raw_content' => 'Evaluasi hasil pengawasan sore',
            'final_content' => 'Evaluasi hasil pengawasan sore',
            'status' => 'submitted',
            'source' => 'web',
            'created_at' => Carbon::parse('2026-10-13 16:30:00'),
        ]);

        $component = Livewire::test(\App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities::class);

        // Ambil record yang dirender di tabel
        $records = $component->instance()->getTable()->getRecords();
        $this->assertCount(3, $records);

        // Pastikan urutan item adalah Pagi (act1) -> Siang (act2) -> Sore (act3)
        $this->assertEquals($act1->id, $records->values()[0]->id);
        $this->assertEquals($act2->id, $records->values()[1]->id);
        $this->assertEquals($act3->id, $records->values()[2]->id);
    }

    public function test_table_has_sortable_created_at_column(): void
    {
        $table = \App\Filament\Resources\DailyActivityResource::table(
            new \Filament\Tables\Table(new \App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities())
        );

        $column = $table->getColumn('created_at');
        $this->assertNotNull($column, 'Kolom created_at harus ada di tabel');
        $this->assertTrue($column->isSortable(), 'Kolom created_at harus sortable');
        $this->assertTrue($column->isToggledHiddenByDefault(), 'Kolom created_at harus hidden default agar tabel tetap ringkas');
    }

    public function test_activities_across_different_dates_are_ordered_with_newest_date_first(): void
    {
        $actOld = DailyActivity::create([
            'user_id' => $this->user->id,
            'activity_date' => '2026-10-10',
            'raw_content' => 'Kegiatan tanggal lampau',
            'final_content' => 'Kegiatan tanggal lampau',
            'status' => 'submitted',
            'source' => 'web',
        ]);

        $actMid = DailyActivity::create([
            'user_id' => $this->user->id,
            'activity_date' => '2026-10-15',
            'raw_content' => 'Kegiatan pertengahan',
            'final_content' => 'Kegiatan pertengahan',
            'status' => 'submitted',
            'source' => 'web',
        ]);

        $actNew = DailyActivity::create([
            'user_id' => $this->user->id,
            'activity_date' => '2026-10-20',
            'raw_content' => 'Kegiatan terbaru',
            'final_content' => 'Kegiatan terbaru',
            'status' => 'submitted',
            'source' => 'web',
        ]);

        $component = Livewire::test(\App\Filament\Resources\DailyActivityResource\Pages\ListDailyActivities::class);
        $records = $component->instance()->getTable()->getRecords();

        $this->assertCount(3, $records);
        $this->assertEquals($actNew->id, $records->values()[0]->id, 'Tanggal terbaru harus di posisi paling atas');
        $this->assertEquals($actMid->id, $records->values()[1]->id, 'Tanggal pertengahan di posisi kedua');
        $this->assertEquals($actOld->id, $records->values()[2]->id, 'Tanggal terlama di posisi paling bawah');
    }
}
