<?php

namespace App\Policies;

use App\Models\Report;
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
        // FK reports.reporting_period_id cascade: periode berlaporan tidak boleh dihapus.
        return $user->hasRole('Admin') && ! Report::where('reporting_period_id', $period->id)->exists();
    }
}
