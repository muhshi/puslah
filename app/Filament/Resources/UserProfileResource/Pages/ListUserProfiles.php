<?php

namespace App\Filament\Resources\UserProfileResource\Pages;

use Filament\Schemas\Components\Tabs\Tab;
use App\Models\UserProfile;
use Filament\Actions\CreateAction;
use App\Filament\Resources\UserProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class ListUserProfiles extends ListRecords
{
    protected static string $resource = UserProfileResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'semua' => Tab::make('Semua')->badge(UserProfile::count())
        ];

        $roles = Role::whereNotIn('name', ['super_admin', 'Kepala', 'Kasubag'])->get();
        
        foreach ($roles as $role) {
            $count = UserProfile::whereHas('user.roles', fn ($q) => $q->where('name', $role->name))->count();
            $tabs[$role->name] = Tab::make($role->name)
                ->badge($count)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('user.roles', fn ($q) => $q->where('name', $role->name)));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
