<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acolhimento_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acolhimento_id')->constrained('acolhimentos')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->string('situacao_resultante', 30);
            $table->timestampTz('efetiva_em');
            $table->text('motivo')->nullable();
            $table->text('fundamento')->nullable();
            $table->string('local_destino')->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('recorded_at');
            $table->uuid('idempotency_key');

            $table->unique(['acolhimento_id', 'idempotency_key'], 'acolhimento_movements_idempotency_unique');
            $table->unique(['id', 'acolhimento_id'], 'acolhimento_movements_id_episode_unique');
            $table->index(
                ['acolhimento_id', 'efetiva_em', 'id'],
                'acolhimento_movements_timeline_index',
            );
            $table->index('created_by', 'acolhimento_movements_creator_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE acolhimentos
ADD CONSTRAINT acolhimentos_closing_movement_foreign
FOREIGN KEY (encerrado_por_movimentacao_id, id)
REFERENCES acolhimento_movimentacoes (id, acolhimento_id)
ON DELETE RESTRICT
SQL);
            DB::statement(<<<'SQL'
ALTER TABLE acolhimento_movimentacoes
ADD CONSTRAINT acolhimento_movements_type_state_check
CHECK (
    (tipo = 'ingresso' AND situacao_resultante = 'na_unidade') OR
    (tipo = 'evasao' AND situacao_resultante = 'evadido') OR
    (tipo = 'retorno' AND situacao_resultante = 'na_unidade') OR
    (tipo = 'internacao' AND situacao_resultante = 'internado') OR
    (tipo = 'desacolhimento' AND situacao_resultante = 'desacolhido')
)
SQL);
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION reject_acolhimento_movement_changes() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'acolhimento_movimentacoes is append-only';
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER acolhimento_movements_no_update_delete BEFORE UPDATE OR DELETE ON acolhimento_movimentacoes FOR EACH ROW EXECUTE FUNCTION reject_acolhimento_movement_changes()');
            DB::statement('CREATE TRIGGER acolhimento_movements_no_truncate BEFORE TRUNCATE ON acolhimento_movimentacoes FOR EACH STATEMENT EXECUTE FUNCTION reject_acolhimento_movement_changes()');
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION validate_acolhimento_closure_projection() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.encerrado_em IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM acolhimento_movimentacoes
        WHERE id = NEW.encerrado_por_movimentacao_id
          AND acolhimento_id = NEW.id
          AND tipo = 'desacolhimento'
          AND efetiva_em = NEW.encerrado_em
    ) THEN
        RAISE EXCEPTION 'invalid acolhimento closure projection';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER acolhimentos_validate_closure BEFORE UPDATE ON acolhimentos FOR EACH ROW EXECUTE FUNCTION validate_acolhimento_closure_projection()');
        } else {
            Schema::table('acolhimentos', function (Blueprint $table) {
                $table->foreign('encerrado_por_movimentacao_id', 'acolhimentos_closing_movement_foreign')
                    ->references('id')
                    ->on('acolhimento_movimentacoes')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS acolhimentos_validate_closure ON acolhimentos');
            DB::statement('DROP FUNCTION IF EXISTS validate_acolhimento_closure_projection()');
            DB::statement('DROP TRIGGER IF EXISTS acolhimento_movements_no_truncate ON acolhimento_movimentacoes');
            DB::statement('DROP TRIGGER IF EXISTS acolhimento_movements_no_update_delete ON acolhimento_movimentacoes');
            DB::statement('DROP FUNCTION IF EXISTS reject_acolhimento_movement_changes()');
        }

        Schema::table('acolhimentos', function (Blueprint $table) {
            $table->dropForeign('acolhimentos_closing_movement_foreign');
        });

        Schema::dropIfExists('acolhimento_movimentacoes');
    }
};
