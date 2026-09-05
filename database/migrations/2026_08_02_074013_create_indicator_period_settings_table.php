<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Target, satuan, dan pagu anggaran (RO) kini per-periode (Fase 21).
     * Sebelumnya: indikator punya target/unit tetap; pagu RO ada di tabel
     * terpisah `indicator_budgets`. Migration ini membuat satu tabel terpadu
     * dan memigrasikan data lama sebagai nilai awal per periode.
     */
    public function up(): void
    {
        Schema::create('indicator_period_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporting_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('target', 12, 2)->nullable();
            $table->string('unit', 255)->nullable();
            $table->decimal('pagu_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['indicator_id', 'reporting_period_id'], 'ind_period_setting_unique');
        });

        $this->backfill();
    }

    /**
     * Salin target/satuan indikator ke periode aktif, dan salin seluruh pagu
     * dari `indicator_budgets` ke setting periode terkait. Aman bila tabel
     * sumber kosong (mis. lingkungan testing fresh).
     */
    private function backfill(): void
    {
        $activePeriod = DB::table('reporting_periods')->where('is_active', true)->value('id');

        if ($activePeriod && DB::table('indicators')->exists()) {
            $rows = DB::table('indicators')
                ->select(['id', 'target', 'unit'])
                ->get()
                ->map(fn ($i) => [
                    'indicator_id' => $i->id,
                    'reporting_period_id' => $activePeriod,
                    'target' => $i->target,
                    'unit' => $i->unit,
                    'pagu_amount' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            DB::table('indicator_period_settings')->insert($rows);
        }

        foreach (DB::table('indicator_budgets')->get() as $budget) {
            DB::table('indicator_period_settings')->updateOrInsert(
                [
                    'indicator_id' => $budget->indicator_id,
                    'reporting_period_id' => $budget->reporting_period_id,
                ],
                [
                    'pagu_amount' => $budget->budget_amount,
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_period_settings');
    }
};
