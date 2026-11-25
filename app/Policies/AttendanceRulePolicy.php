<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AttendanceRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceRulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AttendanceRule');
    }

    public function view(AuthUser $authUser, AttendanceRule $attendanceRule): bool
    {
        return $authUser->can('View:AttendanceRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AttendanceRule');
    }

    public function update(AuthUser $authUser, AttendanceRule $attendanceRule): bool
    {
        return $authUser->can('Update:AttendanceRule');
    }

    public function delete(AuthUser $authUser, AttendanceRule $attendanceRule): bool
    {
        return $authUser->can('Delete:AttendanceRule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AttendanceRule');
    }

    public function restore(AuthUser $authUser, AttendanceRule $attendanceRule): bool
    {
        return $authUser->can('Restore:AttendanceRule');
    }

    public function forceDelete(AuthUser $authUser, AttendanceRule $attendanceRule): bool
    {
        return $authUser->can('ForceDelete:AttendanceRule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AttendanceRule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AttendanceRule');
    }

    public function replicate(AuthUser $authUser, AttendanceRule $attendanceRule): bool
    {
        return $authUser->can('Replicate:AttendanceRule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AttendanceRule');
    }

}