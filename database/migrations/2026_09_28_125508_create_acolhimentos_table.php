<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acolhimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crianca_id')->constrained('criancas')->restrictOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->timestampTz('ingresso_em');
            $table->text('motivo');
            $table->text('fundamento')->nullable();
            $table->string('origem_codigo', 50);
            $table->string('origem_complemento')->nullable();
            $table->string('orgao_condutor_codigo', 50);
            $table->string('orgao_condutor_complemento')->nullable();
            $table->string('pessoa_condutora');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('recorded_at');
            $table->timestampTz('encerrado_em')->nullable();
            $table->unsignedBigInteger('encerrado_por_movimentacao_id')->nullable();
            $table->uuid('idempotency_key');

            $table->unique(
                ['crianca_id', 'unidade_id', 'idempotency_key'],
                'acolhimentos_child_unit_idempotency_unique',
            );
            $table->index(['crianca_id', 'ingresso_em'], 'acolhimentos_child_entry_index');
            $table->index('unidade_id', 'acolhimentos_unit_index');
            $table->index('created_by', 'acolhimentos_creator_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE acolhimentos
ADD CONSTRAINT acolhimentos_origin_code_check
CHECK (origem_codigo IN ('poder_judiciario', 'conselho_tutelar', 'ministerio_publico', 'defensoria_publica', 'rede_socioassistencial', 'rede_saude', 'familia_responsavel', 'outro'))
SQL);
            DB::statement(<<<'SQL'
ALTER TABLE acolhimentos
ADD CONSTRAINT acolhimentos_origin_other_check
CHECK ((origem_codigo = 'outro' AND origem_complemento IS NOT NULL AND btrim(origem_complemento) <> '') OR (origem_codigo <> 'outro' AND origem_complemento IS NULL))
SQL);
            DB::statement(<<<'SQL'
ALTER TABLE acolhimentos
ADD CONSTRAINT acolhimentos_conductor_code_check
CHECK (orgao_condutor_codigo IN ('conselho_tutelar', 'poder_judiciario', 'seguranca_publica', 'rede_socioassistencial', 'rede_saude', 'outro'))
SQL);
            DB::statement(<<<'SQL'
ALTER TABLE acolhimentos
ADD CONSTRAINT acolhimentos_conductor_other_check
CHECK ((orgao_condutor_codigo = 'outro' AND orgao_condutor_complemento IS NOT NULL AND btrim(orgao_condutor_complemento) <> '') OR (orgao_condutor_codigo <> 'outro' AND orgao_condutor_complemento IS NULL))
SQL);
            DB::statement(<<<'SQL'
ALTER TABLE acolhimentos
ADD CONSTRAINT acolhimentos_closure_projection_check
CHECK ((encerrado_em IS NULL AND encerrado_por_movimentacao_id IS NULL) OR (encerrado_em IS NOT NULL AND encerrado_por_movimentacao_id IS NOT NULL))
SQL);
            DB::statement(<<<'SQL'
CREATE UNIQUE INDEX acolhimentos_one_open_per_child_unit
ON acolhimentos (crianca_id, unidade_id)
WHERE encerrado_em IS NULL
SQL);
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_acolhimento_history() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP IN ('DELETE', 'TRUNCATE') THEN
        RAISE EXCEPTION 'acolhimentos history is append-only';
    END IF;

    IF OLD.encerrado_em IS NOT NULL
       OR NEW.encerrado_em IS NULL
       OR NEW.encerrado_por_movimentacao_id IS NULL
       OR NEW.crianca_id IS DISTINCT FROM OLD.crianca_id
       OR NEW.unidade_id IS DISTINCT FROM OLD.unidade_id
       OR NEW.ingresso_em IS DISTINCT FROM OLD.ingresso_em
       OR NEW.motivo IS DISTINCT FROM OLD.motivo
       OR NEW.fundamento IS DISTINCT FROM OLD.fundamento
       OR NEW.origem_codigo IS DISTINCT FROM OLD.origem_codigo
       OR NEW.origem_complemento IS DISTINCT FROM OLD.origem_complemento
       OR NEW.orgao_condutor_codigo IS DISTINCT FROM OLD.orgao_condutor_codigo
       OR NEW.orgao_condutor_complemento IS DISTINCT FROM OLD.orgao_condutor_complemento
       OR NEW.pessoa_condutora IS DISTINCT FROM OLD.pessoa_condutora
       OR NEW.created_by IS DISTINCT FROM OLD.created_by
       OR NEW.recorded_at IS DISTINCT FROM OLD.recorded_at
       OR NEW.idempotency_key IS DISTINCT FROM OLD.idempotency_key THEN
        RAISE EXCEPTION 'acolhimentos history is append-only';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER acolhimentos_no_update_delete BEFORE UPDATE OR DELETE ON acolhimentos FOR EACH ROW EXECUTE FUNCTION protect_acolhimento_history()');
            DB::statement('CREATE TRIGGER acolhimentos_no_truncate BEFORE TRUNCATE ON acolhimentos FOR EACH STATEMENT EXECUTE FUNCTION protect_acolhimento_history()');
        } else {
            Schema::table('acolhimentos', function (Blueprint $table) {
                $table->unique(['crianca_id', 'unidade_id', 'encerrado_em'], 'acolhimentos_open_fallback_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS acolhimentos_no_truncate ON acolhimentos');
            DB::statement('DROP TRIGGER IF EXISTS acolhimentos_no_update_delete ON acolhimentos');
            DB::statement('DROP FUNCTION IF EXISTS protect_acolhimento_history()');
        }

        Schema::dropIfExists('acolhimentos');
    }
};
