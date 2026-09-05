<?php

namespace App\Policies;

use App\Models\Risk;
use App\Models\User;

class RiskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Pelapor Unit hanya boleh melihat risiko dari laporan unitnya sendiri (§14.4).
     */
    public function view(User $user, Risk $risk): bool
    {
        if ($user->hasRole('Admin') || $user->hasRole('Verifikator') || $user->hasRole('Pimpinan') || $user->hasRole('Auditor')) {
            return true;
        }

        return $user->unit_id === $risk->report->unit_id;
    }

    /**
     * Hanya Admin/Verifikator/Pimpinan yang boleh mengubah status/PIC/tenggat tindak lanjut.
     * Pelapor Unit bersifat read-only terhadap risiko (§14.4).
     */
    public function update(User $user, Risk $risk): bool
    {
        return $user->hasRole('Admin') || $user->hasRole('Verifikator') || $user->hasRole('Pimpinan');
    }
}
