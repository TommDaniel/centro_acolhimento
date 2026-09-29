<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acolhimentos', function (Blueprint $table) {
            $table->string('vara_snapshot')->nullable();
            $table->string('comarca_snapshot')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_acolhimento_judicial_context_snapshot() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.vara_snapshot IS DISTINCT FROM OLD.vara_snapshot
       OR NEW.comarca_snapshot IS DISTINCT FROM OLD.comarca_snapshot THEN
        RAISE EXCEPTION 'acolhimento judicial context snapshot is immutable';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER acolhimentos_judicial_context_snapshot_immutable BEFORE UPDATE ON acolhimentos FOR EACH ROW EXECUTE FUNCTION protect_acolhimento_judicial_context_snapshot()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS acolhimentos_judicial_context_snapshot_immutable ON acolhimentos');
            DB::statement('DROP FUNCTION IF EXISTS protect_acolhimento_judicial_context_snapshot()');
        }

        Schema::table('acolhimentos', function (Blueprint $table) {
            $table->dropColumn(['vara_snapshot', 'comarca_snapshot']);
        });
    }
};
