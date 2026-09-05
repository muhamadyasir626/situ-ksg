<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Granularitas laporan berubah dari triwulan ke bulan (Fase 22). Kolom `quarter`
     * dipertahankan (kini murni diturunkan dari `month`, `ceil(month/3)`), tapi unique
     * constraint kini mengunci per bulan supaya satu indikator+unit+periode bisa punya
     * hingga 12 laporan/tahun (bukan 4).
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('month')->nullable()->after('quarter');
        });

        // PHP-level backfill agar kompatibel lintas database (MySQL & SQLite test),
        // pola yang sama dengan backfill `quarter` di Fase 21.
        foreach (DB::table('reports')->whereNull('month')->orderBy('id')->get() as $report) {
            $dateString = $report->submitted_at ?: $report->created_at;
            if (! $dateString) {
                continue;
            }

            try {
                $month = (int) Carbon::parse($dateString)->format('n');
            } catch (Throwable $e) {
                continue;
            }

            DB::table('reports')->where('id', $report->id)->update([
                'month' => $month,
                'quarter' => (int) ceil($month / 3),
            ]);
        }

        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_unit_indicator_period_quarter_unique');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->unique(
                ['unit_id', 'indicator_id', 'reporting_period_id', 'month'],
                'reports_unit_indicator_period_month_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_unit_indicator_period_month_unique');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->unique(
                ['unit_id', 'indicator_id', 'reporting_period_id', 'quarter'],
                'reports_unit_indicator_period_quarter_unique'
            );
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('month');
        });
    }
};
