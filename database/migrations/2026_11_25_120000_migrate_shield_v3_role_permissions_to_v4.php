<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $v4Permissions = Permission::query()
            ->where('name', 'like', '%:%')
            ->where('name', 'not like', '%::%')
            ->get()
            ->keyBy('name');

        if ($v4Permissions->isEmpty() && Role::query()->exists()) {
            Artisan::call('shield:generate', [
                '--all' => true,
                '--option' => 'permissions',
                '--panel' => 'admin',
                '--no-interaction' => true,
            ]);

            $v4Permissions = Permission::query()
                ->where('name', 'like', '%:%')
                ->where('name', 'not like', '%::%')
                ->get()
                ->keyBy('name');
        }

        if ($v4Permissions->isEmpty()) {
            return;
        }

        $roles = Role::with('permissions')->get();

        foreach ($roles as $role) {
            if ($role->name === 'super_admin') {
                $role->syncPermissions($v4Permissions->values());

                continue;
            }

            $targetNames = collect();

            foreach ($role->permissions as $permission) {
                $name = $permission->name;

                if ($v4Permissions->has($name)) {
                    $targetNames->push($name);

                    continue;
                }

                $mapped = $this->mapV3ToV4($name);
                if ($mapped && $v4Permissions->has($mapped)) {
                    $targetNames->push($mapped);
                }
            }

            if ($targetNames->isNotEmpty()) {
                $resolved = $targetNames->unique()
                    ->map(fn (string $name) => $v4Permissions->get($name))
                    ->filter();

                $role->syncPermissions($resolved);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non-destructive permission translation; intentionally left as no-op.
    }

    private function mapV3ToV4(string $perm): ?string
    {
        if (str_starts_with($perm, 'page_')) {
            return 'View:'.substr($perm, 5);
        }

        if (str_starts_with($perm, 'widget_')) {
            return 'View:'.substr($perm, 7);
        }

        $prefixes = [
            'view_any_' => 'ViewAny',
            'restore_any_' => 'RestoreAny',
            'delete_any_' => 'DeleteAny',
            'force_delete_any_' => 'ForceDeleteAny',
            'force_delete_' => 'ForceDelete',
            'view_' => 'View',
            'create_' => 'Create',
            'update_' => 'Update',
            'restore_' => 'Restore',
            'delete_' => 'Delete',
        ];

        foreach ($prefixes as $prefix => $action) {
            if (str_starts_with($perm, $prefix)) {
                $model = substr($perm, strlen($prefix));
                $model = $model === 'shield::role'
                    ? 'Role'
                    : Str::studly(str_replace('::', '_', $model));

                return "{$action}:{$model}";
            }
        }

        return null;
    }
};
