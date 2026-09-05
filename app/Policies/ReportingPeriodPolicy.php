<?php

namespace App\Policies;

use App\Models\ReportingPeriod;
use App\Models\User;

class ReportingPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, ReportingPeriod $period): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, ReportingPeriod $period): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, ReportingPeriod $period): bool
    {
        return $user->hasRole('Admin');
    }
}
