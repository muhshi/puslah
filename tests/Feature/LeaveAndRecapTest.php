<?php

namespace Tests\Feature;

use App\Filament\Pages\AttendanceRecap;
use App\Filament\Resources\LeaveResource\Pages\CreateLeave;
use App\Filament\Resources\LeaveResource\Pages\EditLeave;
use App\Filament\Resources\LeaveResource\Pages\ListLeaves;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveAndRecapTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Organik', 'guard_name' => 'web']);

        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->pegawai = User::factory()->create(['name' => 'Budi Santoso']);
        $this->pegawai->assignRole('Mitra');
    }

    public function test_can_render_leave_list_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(ListLeaves::class)
            ->assertSuccessful();
    }

    public function test_user_can_submit_izin()
    {
        $this->actingAs($this->pegawai);

        Livewire::test(CreateLeave::class)
            ->fillForm([
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-12',
                'reason' => 'Keperluan keluarga mendesak',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('leaves', [
            'user_id' => $this->pegawai->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
            'status' => 'pending',
            'reason' => 'Keperluan keluarga mendesak',
        ]);
    }

    public function test_admin_can_approve_izin()
    {
        $this->actingAs($this->admin);

        $leave = Leave::create([
            'user_id' => $this->pegawai->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
            'status' => 'pending',
            'reason' => 'Sakit',
        ]);

        Livewire::test(EditLeave::class, ['record' => $leave->getKey()])
            ->fillForm([
                'status' => 'approved',
                'note' => 'Disetujui semoga lekas sembuh',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'approved',
            'note' => 'Disetujui semoga lekas sembuh',
        ]);
    }

    public function test_attendance_recap_calculates_actual_attendance_numbers()
    {
        $this->actingAs($this->admin);

        $survey = Survey::create([
            'name' => 'Survei Evaluasi SE2026',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'is_active' => true,
        ]);

        SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $this->pegawai->id,
        ]);

        // Buat 2 record presensi untuk pegawai ini
        Attendance::create([
            'user_id' => $this->pegawai->id,
            'schedule_latitude' => -6.89,
            'schedule_longitude' => 110.63,
            'schedule_start_time' => '07:30:00',
            'schedule_end_time' => '16:00:00',
            'start_latitude' => -6.89,
            'start_longitude' => 110.63,
            'start_time' => '07:45:00', // Terlambat
            'end_time' => '16:30:00',
            'created_at' => '2026-10-02 07:45:00',
        ]);

        Attendance::create([
            'user_id' => $this->pegawai->id,
            'schedule_latitude' => -6.89,
            'schedule_longitude' => 110.63,
            'schedule_start_time' => '07:30:00',
            'schedule_end_time' => '16:00:00',
            'start_latitude' => -6.89,
            'start_longitude' => 110.63,
            'start_time' => '07:20:00', // Tepat waktu
            'end_time' => '16:00:00',
            'created_at' => '2026-10-03 07:20:00',
        ]);

        $test = Livewire::test(AttendanceRecap::class)
            ->set('surveyId', $survey->id);

        $record = $test->instance()->getTable()->getRecords()->first();
        $this->assertNotNull($record);
        $this->assertEquals(1, $record->present_count);
        $this->assertEquals(1, $record->late_count);

        $test->assertSuccessful()
            ->assertCanSeeTableRecords([$this->pegawai]);
    }

    public function test_survey_with_attendance_activity_scope()
    {
        // Survei 1: ada presensi
        $surveyWithAtt = Survey::create([
            'name' => 'Survei Dengan Presensi',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'is_active' => true,
        ]);
        SurveyUser::create([
            'survey_id' => $surveyWithAtt->id,
            'user_id' => $this->pegawai->id,
        ]);
        Attendance::create([
            'user_id' => $this->pegawai->id,
            'schedule_latitude' => -6.89,
            'schedule_longitude' => 110.63,
            'schedule_start_time' => '07:30:00',
            'schedule_end_time' => '16:00:00',
            'start_latitude' => -6.89,
            'start_longitude' => 110.63,
            'start_time' => '07:30:00',
            'end_time' => '16:00:00',
            'created_at' => '2026-10-02 07:30:00',
        ]);

        // Survei 2: tidak ada presensi
        $surveyNoAtt = Survey::create([
            'name' => 'Survei Tanpa Presensi',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'is_active' => true,
        ]);
        $anotherUser = User::factory()->create();
        SurveyUser::create([
            'survey_id' => $surveyNoAtt->id,
            'user_id' => $anotherUser->id,
        ]);

        $activeSurveysWithAtt = Survey::withAttendanceActivity()->get();

        $this->assertTrue($activeSurveysWithAtt->contains('id', $surveyWithAtt->id));
        $this->assertFalse($activeSurveysWithAtt->contains('id', $surveyNoAtt->id));
    }
}
