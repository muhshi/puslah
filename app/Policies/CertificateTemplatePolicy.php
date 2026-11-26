<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CertificateTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificateTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CertificateTemplate');
    }

    public function view(AuthUser $authUser, CertificateTemplate $certificateTemplate): bool
    {
        return $authUser->can('View:CertificateTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CertificateTemplate');
    }

    public function update(AuthUser $authUser, CertificateTemplate $certificateTemplate): bool
    {
        return $authUser->can('Update:CertificateTemplate');
    }

    public function delete(AuthUser $authUser, CertificateTemplate $certificateTemplate): bool
    {
        return $authUser->can('Delete:CertificateTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CertificateTemplate');
    }

    public function restore(AuthUser $authUser, CertificateTemplate $certificateTemplate): bool
    {
        return $authUser->can('Restore:CertificateTemplate');
    }

    public function forceDelete(AuthUser $authUser, CertificateTemplate $certificateTemplate): bool
    {
        return $authUser->can('ForceDelete:CertificateTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CertificateTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CertificateTemplate');
    }

    public function replicate(AuthUser $authUser, CertificateTemplate $certificateTemplate): bool
    {
        return $authUser->can('Replicate:CertificateTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CertificateTemplate');
    }

}