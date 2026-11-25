<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\UserProfile;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserProfilePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:UserProfile');
    }

    public function view(AuthUser $authUser, UserProfile $userProfile): bool
    {
        return $authUser->can('View:UserProfile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserProfile');
    }

    public function update(AuthUser $authUser, UserProfile $userProfile): bool
    {
        return $authUser->can('Update:UserProfile');
    }

    public function delete(AuthUser $authUser, UserProfile $userProfile): bool
    {
        return $authUser->can('Delete:UserProfile');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:UserProfile');
    }

    public function restore(AuthUser $authUser, UserProfile $userProfile): bool
    {
        return $authUser->can('Restore:UserProfile');
    }

    public function forceDelete(AuthUser $authUser, UserProfile $userProfile): bool
    {
        return $authUser->can('ForceDelete:UserProfile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:UserProfile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:UserProfile');
    }

    public function replicate(AuthUser $authUser, UserProfile $userProfile): bool
    {
        return $authUser->can('Replicate:UserProfile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:UserProfile');
    }

}