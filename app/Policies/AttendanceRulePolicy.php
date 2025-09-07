<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AttendanceRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceRulePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_attendance::rule');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttendanceRule $attendanceRule): bool
    {
        return $user->can('view_attendance::rule');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_attendance::rule');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AttendanceRule $attendanceRule): bool
    {
        return $user->can('update_attendance::rule');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AttendanceRule $attendanceRule): bool
    {
        return $user->can('delete_attendance::rule');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_attendance::rule');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AttendanceRule $attendanceRule): bool
    {
        return $user->can('{{ ForceDelete }}');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('{{ ForceDeleteAny }}');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AttendanceRule $attendanceRule): bool
    {
        return $user->can('restore_attendance::rule');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_attendance::rule');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AttendanceRule $attendanceRule): bool
    {
        return $user->can('{{ Replicate }}');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('{{ Reorder }}');
    }
}
