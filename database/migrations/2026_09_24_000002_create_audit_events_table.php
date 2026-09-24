<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('result', 20);
            $table->json('changed_fields')->default('[]');
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');

            $table->index(['actor_id', 'occurred_at'], 'audit_actor_occurred_index');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'audit_subject_occurred_index');
            $table->index('correlation_id', 'audit_correlation_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_result_check CHECK (result IN ('success', 'denied'))");
            DB::statement('CREATE INDEX audit_unit_occurred_index ON audit_events (unidade_id, occurred_at DESC, id DESC)');
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION reject_audit_event_changes() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'audit_events is append-only';
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER audit_events_no_update_delete BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION reject_audit_event_changes()');
            DB::statement('CREATE TRIGGER audit_events_no_truncate BEFORE TRUNCATE ON audit_events FOR EACH STATEMENT EXECUTE FUNCTION reject_audit_event_changes()');
        } else {
            Schema::table('audit_events', function (Blueprint $table) {
                $table->index(['unidade_id', 'occurred_at', 'id'], 'audit_unit_occurred_index');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_events_no_truncate ON audit_events');
            DB::statement('DROP TRIGGER IF EXISTS audit_events_no_update_delete ON audit_events');
            DB::statement('DROP FUNCTION IF EXISTS reject_audit_event_changes()');
        }

        Schema::dropIfExists('audit_events');
    }
};
