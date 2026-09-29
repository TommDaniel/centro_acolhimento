<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crianca_informacoes_escolares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crianca_id')->constrained('criancas')->restrictOnDelete();
            $table->foreignId('versao_anterior_id')->nullable()->constrained('crianca_informacoes_escolares')->restrictOnDelete();
            $table->string('situacao_codigo', 50);
            $table->string('situacao_complemento')->nullable();
            $table->string('escola_nome')->nullable();
            $table->string('rede_codigo', 30)->nullable();
            $table->string('rede_complemento')->nullable();
            $table->string('matricula', 100)->nullable();
            $table->string('ano_serie', 100)->nullable();
            $table->string('turma', 100)->nullable();
            $table->string('turno_codigo', 30)->nullable();
            $table->string('turno_complemento')->nullable();
            $table->date('vigente_em')->nullable();
            $table->string('fonte_codigo', 50);
            $table->string('fonte_complemento')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('recorded_at');
            $table->uuid('idempotency_key');

            $table->unique('versao_anterior_id', 'school_information_previous_unique');
            $table->unique(['id', 'crianca_id'], 'school_information_id_child_unique');
            $table->unique(['crianca_id', 'idempotency_key'], 'school_information_child_idempotency_unique');
            $table->index(['versao_anterior_id', 'crianca_id'], 'school_information_previous_child_index');
            $table->index(['crianca_id', 'id'], 'school_information_child_id_index');
            $table->index('created_by', 'school_information_creator_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_previous_same_child_foreign FOREIGN KEY (versao_anterior_id, crianca_id) REFERENCES crianca_informacoes_escolares (id, crianca_id)');
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_status_check CHECK (situacao_codigo IN ('matriculada', 'nao_matriculada', 'matricula_em_andamento', 'frequencia_interrompida', 'outra', 'nao_informada'))");
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_status_other_check CHECK ((situacao_codigo = 'outra' AND situacao_complemento IS NOT NULL AND btrim(situacao_complemento) <> '') OR (situacao_codigo <> 'outra' AND situacao_complemento IS NULL))");
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_network_check CHECK (rede_codigo IS NULL OR rede_codigo IN ('municipal', 'estadual', 'federal', 'privada', 'outra'))");
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_network_other_check CHECK ((rede_codigo = 'outra' AND rede_complemento IS NOT NULL AND btrim(rede_complemento) <> '') OR (rede_codigo IS DISTINCT FROM 'outra' AND rede_complemento IS NULL))");
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_shift_check CHECK (turno_codigo IS NULL OR turno_codigo IN ('matutino', 'vespertino', 'noturno', 'integral', 'outro'))");
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_shift_other_check CHECK ((turno_codigo = 'outro' AND turno_complemento IS NOT NULL AND btrim(turno_complemento) <> '') OR (turno_codigo IS DISTINCT FROM 'outro' AND turno_complemento IS NULL))");
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_source_check CHECK (fonte_codigo IN ('crianca_adolescente', 'familiar_responsavel', 'escola', 'documento', 'rede_atendimento', 'outra', 'nao_informada'))");
            DB::statement("ALTER TABLE crianca_informacoes_escolares ADD CONSTRAINT school_information_source_other_check CHECK ((fonte_codigo = 'outra' AND fonte_complemento IS NOT NULL AND btrim(fonte_complemento) <> '') OR (fonte_codigo <> 'outra' AND fonte_complemento IS NULL))");
            DB::statement(<<<'SQL'
ALTER TABLE crianca_informacoes_escolares
ADD CONSTRAINT school_information_unknown_is_empty_check
CHECK (situacao_codigo <> 'nao_informada' OR (
    situacao_complemento IS NULL AND escola_nome IS NULL AND rede_codigo IS NULL
    AND rede_complemento IS NULL AND matricula IS NULL AND ano_serie IS NULL
    AND turma IS NULL AND turno_codigo IS NULL AND turno_complemento IS NULL
    AND vigente_em IS NULL
))
SQL);
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_school_information_history() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'crianca_informacoes_escolares history is append-only';
END;
$$;
SQL);
            DB::statement('CREATE TRIGGER school_information_no_update_delete BEFORE UPDATE OR DELETE ON crianca_informacoes_escolares FOR EACH ROW EXECUTE FUNCTION protect_school_information_history()');
            DB::statement('CREATE TRIGGER school_information_no_truncate BEFORE TRUNCATE ON crianca_informacoes_escolares FOR EACH STATEMENT EXECUTE FUNCTION protect_school_information_history()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS school_information_no_truncate ON crianca_informacoes_escolares');
            DB::statement('DROP TRIGGER IF EXISTS school_information_no_update_delete ON crianca_informacoes_escolares');
            DB::statement('DROP FUNCTION IF EXISTS protect_school_information_history()');
        }

        Schema::dropIfExists('crianca_informacoes_escolares');
    }
};
