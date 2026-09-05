<?php

namespace App\Policies;

use App\Models\Indicator;
use App\Models\User;

class IndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Indicator $indicator): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Indicator $indicator): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Indicator $indicator): bool
    {
        return $user->hasRole('Admin');
    }
}
