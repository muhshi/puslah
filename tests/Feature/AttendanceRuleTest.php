<?php

namespace Tests\Feature;

use App\Filament\Resources\AttendanceRuleResource\Pages\CreateAttendanceRule;
use App\Filament\Resources\AttendanceRuleResource\Pages\EditAttendanceRule;
use App\Models\AttendanceRule;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceRuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Survey $survey;
    protected User $mitraUser;
    protected User $organikUser;

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

        $this->survey = Survey::create([
            'name' => 'Survei Pertanian 2026',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-20',
            'is_active' => true,
        ]);

        $this->mitraUser = User::factory()->create(['name' => 'Mitra BPS']);
        $this->mitraUser->assignRole('Mitra');

        $this->organikUser = User::factory()->create(['name' => 'Pegawai Organik BPS']);
        $this->organikUser->assignRole('Organik');

        SurveyUser::create([
            'survey_id' => $this->survey->id,
            'user_id' => $this->mitraUser->id,
        ]);

        SurveyUser::create([
            'survey_id' => $this->survey->id,
            'user_id' => $this->organikUser->id,
        ]);
    }

    public function test_can_render_create_attendance_rule_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAttendanceRule::class)
            ->assertSuccessful()
            ->assertFormFieldExists('survey_id')
            ->assertFormFieldExists('user_ids')
            ->assertFormFieldExists('type')
            ->assertFormFieldExists('start_date')
            ->assertFormFieldExists('end_date')
            ->assertFormFieldExists('reason');
    }

    public function test_selecting_survey_auto_fills_dates_and_reason()
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAttendanceRule::class)
            ->fillForm([
                'survey_id' => $this->survey->id,
            ])
            ->assertFormSet([
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-20',
                'reason' => 'Penugasan Survei: Survei Pertanian 2026',
            ]);
    }

    public function test_can_create_bulk_attendance_rules_for_multiple_users()
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAttendanceRule::class)
            ->fillForm([
                'survey_id' => $this->survey->id,
                'user_ids' => [$this->mitraUser->id, $this->organikUser->id],
                'type' => 'WFA',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-20',
                'reason' => 'WFA Pelaksanaan Lapangan',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('attendance_rules', [
            'user_id' => $this->mitraUser->id,
            'type' => 'WFA',
            'start_date' => '2026-10-10 00:00:00',
            'end_date' => '2026-10-20 00:00:00',
            'reason' => 'WFA Pelaksanaan Lapangan',
            'approved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('attendance_rules', [
            'user_id' => $this->organikUser->id,
            'type' => 'WFA',
            'start_date' => '2026-10-10 00:00:00',
            'end_date' => '2026-10-20 00:00:00',
            'reason' => 'WFA Pelaksanaan Lapangan',
            'approved_by' => $this->admin->id,
        ]);

        $this->assertEquals(2, AttendanceRule::count());
    }

    public function test_can_edit_existing_attendance_rule()
    {
        $this->actingAs($this->admin);

        $rule = AttendanceRule::create([
            'user_id' => $this->mitraUser->id,
            'type' => 'WFA',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-20',
            'reason' => 'Old Reason',
            'approved_by' => $this->admin->id,
        ]);

        Livewire::test(EditAttendanceRule::class, ['record' => $rule->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('user_id')
            ->fillForm([
                'type' => 'BANNED',
                'reason' => 'Updated Reason Banned',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('attendance_rules', [
            'id' => $rule->id,
            'type' => 'BANNED',
            'reason' => 'Updated Reason Banned',
        ]);
    }
}
