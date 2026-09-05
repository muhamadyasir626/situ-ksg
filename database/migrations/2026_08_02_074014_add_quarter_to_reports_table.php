<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Triwulan (Q1–Q4) diturunkan dari tanggal input pelaporan (Fase 21).
     * Kolom ini nullable + di-backfill dari submitted_at/created_at untuk
     * laporan yang sudah ada. Unique constraint (unit, indicator, period)
     * diperluas menyertakan quarter di Fase 21 item alur input triwulan.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('quarter')->nullable()->after('reporting_period_id');
        });

        // PHP-level backfill agar kompatibel lintas database (MySQL & SQLite test).
        foreach (DB::table('reports')->whereNull('quarter')->orderBy('id')->get() as $report) {
            $dateString = $report->submitted_at ?: $report->created_at;
            if (! $dateString) {
                continue;
            }

            try {
                $month = (int) Carbon::parse($dateString)->format('n');
            } catch (Throwable $e) {
                continue;
            }

            $quarter = (int) floor(($month - 1) / 3) + 1;
            DB::table('reports')->where('id', $report->id)->update(['quarter' => $quarter]);
        }
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('quarter');
        });
    }
};
