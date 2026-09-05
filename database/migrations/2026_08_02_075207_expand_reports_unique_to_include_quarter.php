<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 21: satu laporan per (unit, indikator, periode tahunan, triwulan).
     * Unique constraint diperluas menyertakan `quarter`. Index mandiri pada
     * `unit_id` ditambahkan dulu karena FK unit_id sebelumnya menumpang pada
     * unique index lama (MySQL melarang drop index yang dipakai FK).
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->index('unit_id', 'reports_unit_id_index');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_unit_id_indicator_id_reporting_period_id_unique');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->unique(
                ['unit_id', 'indicator_id', 'reporting_period_id', 'quarter'],
                'reports_unit_indicator_period_quarter_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_unit_indicator_period_quarter_unique');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->unique(['unit_id', 'indicator_id', 'reporting_period_id'], 'reports_unit_id_indicator_id_reporting_period_id_unique');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_unit_id_index');
        });
    }
};
