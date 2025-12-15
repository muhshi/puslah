<?php

namespace App\Policies;

use App\Models\User;
use App\Models\LaporanPerjalananDinas;
use Illuminate\Auth\Access\HandlesAuthorization;

class LaporanPerjalananDinasPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $user->can('view_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $user->can('update_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $user->can('delete_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $user->can('restore_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, LaporanPerjalananDinas $laporanPerjalananDinas): bool
    {
        return $user->can('replicate_laporan::perjalanan::dinas');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_laporan::perjalanan::dinas');
    }
}
