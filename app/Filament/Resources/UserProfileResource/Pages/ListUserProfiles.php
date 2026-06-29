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
        $tabs = ['semua' => Tab::make('Semua')];

        $roles = Role::all();
        
        foreach ($roles as $role) {
            $tabs[$role->name] = Tab::make($role->name)
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
