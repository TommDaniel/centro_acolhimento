<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 20);
            $table->text('secret');
            $table->unsignedBigInteger('last_accepted_time_step')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'version']);
            $table->index(['user_id', 'state']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE mfa_enrollments ADD CONSTRAINT mfa_enrollments_state_check CHECK (state IN ('pending', 'active', 'revoked'))");
            DB::statement("CREATE UNIQUE INDEX mfa_one_pending_per_user ON mfa_enrollments (user_id) WHERE state = 'pending'");
            DB::statement("CREATE UNIQUE INDEX mfa_one_active_per_user ON mfa_enrollments (user_id) WHERE state = 'active'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_enrollments');
    }
};
