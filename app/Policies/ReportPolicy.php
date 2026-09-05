<?php

namespace App\Policies;

use App\Models\Indicator;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->hasRole('Admin') || $user->hasRole('Verifikator') || $user->hasRole('Pimpinan') || $user->hasRole('Auditor')) {
            return true;
        }

        return $user->unit_id === $report->unit_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin') || $user->hasRole('Pelapor Unit');
    }

    public function update(User $user, Report $report): bool
    {
        if ($report->status === 'Terverifikasi') {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Pelapor Unit') && $user->unit_id === $report->unit_id) {
            return $report->status === 'Draft' || $report->status === 'Dikembalikan';
        }

        return false;
    }

    public function delete(User $user, Report $report): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return $user->hasRole('Pelapor Unit')
            && $user->unit_id === $report->unit_id
            && $report->status === 'Draft';
    }

    /**
     * Pelapor hanya boleh membuat laporan untuk indikator yang ditugaskan ke unitnya (§3.2).
     */
    public function createForIndicator(User $user, Indicator $indicator): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if (! $user->hasRole('Pelapor Unit') || ! $user->unit_id) {
            return false;
        }

        return $indicator->units()->where('units.id', $user->unit_id)->exists();
    }
}
