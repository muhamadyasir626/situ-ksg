<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $targetUser): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $targetUser): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can delete/deactivate the model.
     */
    public function delete(User $user, User $targetUser): bool
    {
        return $user->hasRole('Admin') && $user->id !== $targetUser->id;
    }

    /**
     * Determine whether the user can assign roles to another user.
     */
    public function assignRole(User $user, User $targetUser): bool
    {
        return $user->hasRole('Admin');
    }
}
