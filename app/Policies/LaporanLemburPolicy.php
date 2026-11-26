<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LaporanLembur;
use Illuminate\Auth\Access\HandlesAuthorization;

class LaporanLemburPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LaporanLembur');
    }

    public function view(AuthUser $authUser, LaporanLembur $laporanLembur): bool
    {
        return $authUser->can('View:LaporanLembur');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LaporanLembur');
    }

    public function update(AuthUser $authUser, LaporanLembur $laporanLembur): bool
    {
        return $authUser->can('Update:LaporanLembur');
    }

    public function delete(AuthUser $authUser, LaporanLembur $laporanLembur): bool
    {
        return $authUser->can('Delete:LaporanLembur');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LaporanLembur');
    }

    public function restore(AuthUser $authUser, LaporanLembur $laporanLembur): bool
    {
        return $authUser->can('Restore:LaporanLembur');
    }

    public function forceDelete(AuthUser $authUser, LaporanLembur $laporanLembur): bool
    {
        return $authUser->can('ForceDelete:LaporanLembur');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LaporanLembur');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LaporanLembur');
    }

    public function replicate(AuthUser $authUser, LaporanLembur $laporanLembur): bool
    {
        return $authUser->can('Replicate:LaporanLembur');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LaporanLembur');
    }

}