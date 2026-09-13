<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('process_note')->nullable()->after('problem_narrative');
            $table->text('follow_up')->nullable()->after('process_note');
            $table->text('realization_achievement')->nullable()->after('target_achievement');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['process_note', 'follow_up', 'realization_achievement']);
        });
    }
};
