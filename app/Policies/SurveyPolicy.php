<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Survey;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Survey');
    }

    public function view(AuthUser $authUser, Survey $survey): bool
    {
        return $authUser->can('View:Survey');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Survey');
    }

    public function update(AuthUser $authUser, Survey $survey): bool
    {
        return $authUser->can('Update:Survey');
    }

    public function delete(AuthUser $authUser, Survey $survey): bool
    {
        return $authUser->can('Delete:Survey');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Survey');
    }

    public function restore(AuthUser $authUser, Survey $survey): bool
    {
        return $authUser->can('Restore:Survey');
    }

    public function forceDelete(AuthUser $authUser, Survey $survey): bool
    {
        return $authUser->can('ForceDelete:Survey');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Survey');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Survey');
    }

    public function replicate(AuthUser $authUser, Survey $survey): bool
    {
        return $authUser->can('Replicate:Survey');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Survey');
    }

}