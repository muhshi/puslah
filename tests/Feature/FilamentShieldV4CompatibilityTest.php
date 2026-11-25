<?php

use App\Filament\Pages\SystemSettingsPage;
use App\Models\User;
use App\Settings\SystemSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    SystemSettings::fake(SystemSettings::defaults());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Operator', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
});

it('uses Shield v4 PascalCase permission names across all policies without legacy placeholders', function () {
    $policyFiles = glob(app_path('Policies/*.php'));
    expect($policyFiles)->not->toBeEmpty();

    foreach ($policyFiles as $file) {
        $content = file_get_contents($file);
        expect($content)->not->toContain('{{ ForceDelete }}')
            ->and($content)->not->toContain('view_any_')
            ->and($content)->not->toContain('shield::role');
    }
});

it('allows super_admin to access RoleResource and SystemSettingsPage without Gate::before mock', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create();
    $admin->syncRoles(['super_admin']);

    $roleToEdit = Role::where('name', 'Operator')->first();

    $this->actingAs($admin);

    $this->get('/admin/shield/roles')->assertOk();
    $this->get("/admin/shield/roles/{$roleToEdit->id}/edit")->assertOk();
    $this->get('/admin/system-settings')->assertOk();

    expect(SystemSettingsPage::canAccess())->toBeTrue()
        ->and(SystemSettingsPage::shouldRegisterNavigation())->toBeTrue();
});

it('authorizes non-super_admin roles using Shield v4 permissions', function () {
    $permSurvey = Permission::firstOrCreate(['name' => 'ViewAny:Survey', 'guard_name' => 'web']);
    $permSettings = Permission::firstOrCreate(['name' => 'View:SystemSettingsPage', 'guard_name' => 'web']);

    $operatorRole = Role::where('name', 'Operator')->first();
    $operatorRole->syncPermissions([$permSurvey, $permSettings]);

    $operator = User::factory()->create();
    $operator->syncRoles(['Operator']);

    $this->actingAs($operator);

    $this->get('/admin/surveys')->assertOk();
    $this->get('/admin/system-settings')->assertOk();
    $this->get('/admin/shield/roles')->assertForbidden();
});
