<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('invitation_token_hash', 64)->nullable();
        });

        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('deduplication_key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['sent_at', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('invitation_token_hash'));
    }
};
