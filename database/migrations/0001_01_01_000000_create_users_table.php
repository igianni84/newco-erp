<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// operator-auth-foundation 6.1 (design D1) — the generic `users` table block was removed: the
// `Operator` principal (`operators` table) is the sole authenticatable, so the bootstrap `users`
// shell has no future home. The shared `password_reset_tokens` (the operator password broker) and
// `sessions` (the operator session guard) tables are RETAINED. `sessions.user_id` keeps its
// framework-default name — it is a plain nullable index, not a foreign key to any table.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
