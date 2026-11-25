<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LaporanPerjalananDinas;
use Illuminate\Auth\Access\HandlesAuthorization;

class LaporanPerjalananDinasPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LaporanPerjalananDinas');
    }

    public function view(AuthUser $authUser, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $authUser->can('View:LaporanPerjalananDinas');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LaporanPerjalananDinas');
    }

    public function update(AuthUser $authUser, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $authUser->can('Update:LaporanPerjalananDinas');
    }

    public function delete(AuthUser $authUser, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $authUser->can('Delete:LaporanPerjalananDinas');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LaporanPerjalananDinas');
    }

    public function restore(AuthUser $authUser, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $authUser->can('Restore:LaporanPerjalananDinas');
    }

    public function forceDelete(AuthUser $authUser, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $authUser->can('ForceDelete:LaporanPerjalananDinas');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LaporanPerjalananDinas');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LaporanPerjalananDinas');
    }

    public function replicate(AuthUser $authUser, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $authUser->can('Replicate:LaporanPerjalananDinas');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LaporanPerjalananDinas');
    }

}