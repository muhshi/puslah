<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SurveyUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyUserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SurveyUser');
    }

    public function view(AuthUser $authUser, SurveyUser $surveyUser): bool
    {
        return $authUser->can('View:SurveyUser');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SurveyUser');
    }

    public function update(AuthUser $authUser, SurveyUser $surveyUser): bool
    {
        return $authUser->can('Update:SurveyUser');
    }

    public function delete(AuthUser $authUser, SurveyUser $surveyUser): bool
    {
        return $authUser->can('Delete:SurveyUser');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SurveyUser');
    }

    public function restore(AuthUser $authUser, SurveyUser $surveyUser): bool
    {
        return $authUser->can('Restore:SurveyUser');
    }

    public function forceDelete(AuthUser $authUser, SurveyUser $surveyUser): bool
    {
        return $authUser->can('ForceDelete:SurveyUser');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SurveyUser');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SurveyUser');
    }

    public function replicate(AuthUser $authUser, SurveyUser $surveyUser): bool
    {
        return $authUser->can('Replicate:SurveyUser');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SurveyUser');
    }

}