<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Miran\Mksine\Models\GeoState;
use Illuminate\Auth\Access\HandlesAuthorization;

class GeoStatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GeoState');
    }

    public function view(AuthUser $authUser, GeoState $geoState): bool
    {
        return $authUser->can('View:GeoState');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GeoState');
    }

    public function update(AuthUser $authUser, GeoState $geoState): bool
    {
        return $authUser->can('Update:GeoState');
    }

    public function delete(AuthUser $authUser, GeoState $geoState): bool
    {
        return $authUser->can('Delete:GeoState');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GeoState');
    }

    public function restore(AuthUser $authUser, GeoState $geoState): bool
    {
        return $authUser->can('Restore:GeoState');
    }

    public function forceDelete(AuthUser $authUser, GeoState $geoState): bool
    {
        return $authUser->can('ForceDelete:GeoState');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GeoState');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GeoState');
    }

    public function replicate(AuthUser $authUser, GeoState $geoState): bool
    {
        return $authUser->can('Replicate:GeoState');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GeoState');
    }

}