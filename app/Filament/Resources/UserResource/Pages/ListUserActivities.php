<?php

namespace App\Filament\Resources\UserResource\Pages;

use pxlrbt\FilamentActivityLog\Pages\ListActivities;
use App\Filament\Resources\UserResource;

class ListUserActivities extends ListActivities
{
    protected static string $resource = UserResource::class;
}
