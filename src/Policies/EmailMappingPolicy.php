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

        $authConfig = config('mail-mapper.authorization', []);

        // 1) Check configured permissions (gates)
        $permissions = (array) ($authConfig['permissions'] ?? []);
        if (!empty($permissions) && method_exists($user, 'can')) {
            foreach ($permissions as $perm) {
                if ($user->can($perm)) return true;
            }
        }

        // 2) Check configured roles (supports common packages like spatie/laravel-permission)
        $roles = (array) ($authConfig['roles'] ?? []);
        if (!empty($roles)) {
            // hasAnyRole/hasRole (spatie)
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
                return true;
            }

            if (method_exists($user, 'hasRole')) {
                foreach ($roles as $role) {
                    if ($user->hasRole($role)) return true;
                }
            }

            // Fallback: check simple role property or roles collection
            if (property_exists($user, 'role') && in_array($user->role, $roles, true)) {
                return true;
            }
            if (property_exists($user, 'roles') && is_iterable($user->roles)) {
                try {
                    foreach ($user->roles as $r) {
                        $name = is_object($r) && isset($r->name) ? $r->name : $r;
                        if (in_array($name, $roles, true)) return true;
                    }
                } catch (\Throwable $e) {
                    // ignore and continue
                }
            }
        }

        // 3) Convenience super-admin checks if enabled
        if (!empty($authConfig['allow_super_admin'])) {
            if (method_exists($user, 'can') && $user->can('super-admin-only')) return true;
            // common role name
            if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) return true;
            if (property_exists($user, 'is_super_admin') && !empty($user->is_super_admin)) return true;
        }

        // 4) Legacy or host-defined permissions to keep compatibility
        if (method_exists($user, 'can')) {
            if ($user->can('email-mapping-configure')) return true;
            if ($user->can('manage-email-mapper')) return true;
        }

        // Default behavior
        return !empty($authConfig['default_allow']);
    }
}
