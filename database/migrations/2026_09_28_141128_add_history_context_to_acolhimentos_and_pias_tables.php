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
            $table->string('processo_numero_snapshot', 100)->nullable();
            $table->unique(['id', 'crianca_id'], 'acolhimentos_id_child_unique');
        });

        Schema::table('pias', function (Blueprint $table) {
            $table->unsignedBigInteger('acolhimento_id')->nullable();
            $table->index('acolhimento_id', 'pias_acolhimento_index');
            $table->foreign(['acolhimento_id', 'crianca_id'], 'pias_acolhimento_child_foreign')
                ->references(['id', 'crianca_id'])
                ->on('acolhimentos')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_acolhimento_process_snapshot() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.processo_numero_snapshot IS DISTINCT FROM OLD.processo_numero_snapshot THEN
        RAISE EXCEPTION 'acolhimento process snapshot is immutable';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER acolhimentos_process_snapshot_immutable BEFORE UPDATE ON acolhimentos FOR EACH ROW EXECUTE FUNCTION protect_acolhimento_process_snapshot()');
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_pia_episode_link() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.acolhimento_id IS NOT NULL
       AND NEW.acolhimento_id IS DISTINCT FROM OLD.acolhimento_id THEN
        RAISE EXCEPTION 'pia acolhimento link is immutable';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER pias_episode_link_immutable BEFORE UPDATE ON pias FOR EACH ROW EXECUTE FUNCTION protect_pia_episode_link()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS pias_episode_link_immutable ON pias');
            DB::statement('DROP FUNCTION IF EXISTS protect_pia_episode_link()');
            DB::statement('DROP TRIGGER IF EXISTS acolhimentos_process_snapshot_immutable ON acolhimentos');
            DB::statement('DROP FUNCTION IF EXISTS protect_acolhimento_process_snapshot()');
        }

        Schema::table('pias', function (Blueprint $table) {
            $table->dropForeign('pias_acolhimento_child_foreign');
            $table->dropIndex('pias_acolhimento_index');
            $table->dropColumn('acolhimento_id');
        });

        Schema::table('acolhimentos', function (Blueprint $table) {
            $table->dropUnique('acolhimentos_id_child_unique');
            $table->dropColumn('processo_numero_snapshot');
        });
    }
};
