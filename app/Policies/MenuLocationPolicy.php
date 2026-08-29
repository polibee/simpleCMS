<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Miran\Mksine\Models\MenuLocation;
use Illuminate\Auth\Access\HandlesAuthorization;

class MenuLocationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MenuLocation');
    }

    public function view(AuthUser $authUser, MenuLocation $menuLocation): bool
    {
        return $authUser->can('View:MenuLocation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MenuLocation');
    }

    public function update(AuthUser $authUser, MenuLocation $menuLocation): bool
    {
        return $authUser->can('Update:MenuLocation');
    }

    public function delete(AuthUser $authUser, MenuLocation $menuLocation): bool
    {
        return $authUser->can('Delete:MenuLocation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MenuLocation');
    }

    public function restore(AuthUser $authUser, MenuLocation $menuLocation): bool
    {
        return $authUser->can('Restore:MenuLocation');
    }

    public function forceDelete(AuthUser $authUser, MenuLocation $menuLocation): bool
    {
        return $authUser->can('ForceDelete:MenuLocation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MenuLocation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MenuLocation');
    }

    public function replicate(AuthUser $authUser, MenuLocation $menuLocation): bool
    {
        return $authUser->can('Replicate:MenuLocation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MenuLocation');
    }

}