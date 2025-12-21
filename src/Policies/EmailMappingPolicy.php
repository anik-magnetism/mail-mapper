<?php

namespace AnikNinja\MailMapper\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class EmailMappingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any mappings.
     */
    public function viewAny($user): bool
    {
        return $this->hasManagePermission($user);
    }

    /**
     * Determine whether the user can view a mapping.
     */
    public function view($user, $mapping): bool
    {
        return $this->hasManagePermission($user);
    }

    /**
     * Determine whether the user can create mappings.
     */
    public function create($user): bool
    {
        return $this->hasManagePermission($user);
    }

    /**
     * Determine whether the user can update mappings.
     */
    public function update($user, $mapping): bool
    {
        return $this->hasManagePermission($user);
    }

    /**
     * Determine whether the user can delete mappings.
     */
    public function delete($user, $mapping): bool
    {
        return $this->hasManagePermission($user);
    }

    protected function hasManagePermission($user): bool
    {
        if (! $user) {
            return false;
        }

        // Allow host application to control permissions via Gates/permissions like 'email-mapping-configure' or 'super-admin-only'.
        if (method_exists($user, 'can')) {
            if ($user->can('super-admin-only')) return true;
            if ($user->can('email-mapping-configure')) return true;
            if ($user->can('manage-email-mapper')) return true;
        }

        // Default: deny to encourage explicit permission definition by host app
        return false;
    }
}
