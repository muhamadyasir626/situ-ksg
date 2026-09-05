<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporting_period_id')->constrained()->cascadeOnDelete();

            // Kolom E–I (§4)
            $table->decimal('ro_budget_ceiling', 15, 2)->nullable();
            $table->decimal('budget_realization', 15, 2)->nullable();
            $table->string('target_achievement')->nullable();
            $table->text('progress_narrative')->nullable();
            $table->text('problem_narrative')->nullable();

            // Kolom K–N: Identifikasi Risiko (§13.4)
            $table->text('risk_external_narrative')->nullable();
            $table->text('risk_strategic_narrative')->nullable();
            $table->text('risk_fraud_narrative')->nullable();
            $table->text('risk_policy_narrative')->nullable();

            // Status alur
            $table->string('status')->default('Draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // §8 Integritas: satu laporan aktif per unit+indikator+periode
            $table->unique(['unit_id', 'indicator_id', 'reporting_period_id']);
            $table->index(['reporting_period_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
