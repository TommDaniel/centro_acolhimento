<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_attempt_states', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedSmallInteger('cooldown_level')->default(0);
            $table->timestampTz('blocked_until')->nullable();
            $table->timestampTz('last_failed_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE mfa_attempt_states ADD CONSTRAINT mfa_attempt_states_consecutive_failures_check CHECK (consecutive_failures BETWEEN 0 AND 65535)');
            DB::statement('ALTER TABLE mfa_attempt_states ADD CONSTRAINT mfa_attempt_states_cooldown_level_check CHECK (cooldown_level BETWEEN 0 AND 3)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_attempt_states');
    }
};
