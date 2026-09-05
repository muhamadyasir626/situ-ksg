<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationNote;

class VerificationNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Verifikator') || $user->hasRole('Admin');
    }

    public function view(User $user, VerificationNote $verificationNote): bool
    {
        return $user->hasRole('Verifikator') || $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Verifikator') || $user->hasRole('Admin');
    }
}
