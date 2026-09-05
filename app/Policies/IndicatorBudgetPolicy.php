<?php

namespace App\Policies;

use App\Models\IndicatorBudget;
use App\Models\User;

class IndicatorBudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, IndicatorBudget $budget): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, IndicatorBudget $budget): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, IndicatorBudget $budget): bool
    {
        return $user->hasRole('Admin');
    }
}
