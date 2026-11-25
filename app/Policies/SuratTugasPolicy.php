<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SuratTugas;
use Illuminate\Auth\Access\HandlesAuthorization;

class SuratTugasPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SuratTugas');
    }

    public function view(AuthUser $authUser, SuratTugas $suratTugas): bool
    {
        return $authUser->can('View:SuratTugas');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SuratTugas');
    }

    public function update(AuthUser $authUser, SuratTugas $suratTugas): bool
    {
        return $authUser->can('Update:SuratTugas');
    }

    public function delete(AuthUser $authUser, SuratTugas $suratTugas): bool
    {
        return $authUser->can('Delete:SuratTugas');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SuratTugas');
    }

    public function restore(AuthUser $authUser, SuratTugas $suratTugas): bool
    {
        return $authUser->can('Restore:SuratTugas');
    }

    public function forceDelete(AuthUser $authUser, SuratTugas $suratTugas): bool
    {
        return $authUser->can('ForceDelete:SuratTugas');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SuratTugas');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SuratTugas');
    }

    public function replicate(AuthUser $authUser, SuratTugas $suratTugas): bool
    {
        return $authUser->can('Replicate:SuratTugas');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SuratTugas');
    }

}