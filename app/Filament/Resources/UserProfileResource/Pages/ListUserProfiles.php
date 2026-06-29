<?php

namespace App\Filament\Resources\UserProfileResource\Pages;

use App\Filament\Resources\UserProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class ListUserProfiles extends ListRecords
{
    protected static string $resource = UserProfileResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'semua' => Tab::make('Semua')->badge(\App\Models\UserProfile::count())
        ];

        $roles = Role::whereNotIn('name', ['super_admin', 'Kepala', 'Kasubag'])->get();
        
        foreach ($roles as $role) {
            $count = \App\Models\UserProfile::whereHas('user.roles', fn ($q) => $q->where('name', $role->name))->count();
            $tabs[$role->name] = Tab::make($role->name)
                ->badge($count)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('user.roles', fn ($q) => $q->where('name', $role->name)));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
