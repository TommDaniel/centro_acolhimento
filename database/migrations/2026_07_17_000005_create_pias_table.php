<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crianca_id')->constrained('criancas')->cascadeOnDelete();
            $table->text('composicao_familiar')->nullable();
            $table->text('dados_acolhimento')->nullable();
            $table->text('especificidades')->nullable();
            $table->text('informacoes_familia')->nullable();
            $table->text('saude')->nullable();
            $table->text('saude_familiares')->nullable();
            $table->text('educacao_menor')->nullable();
            $table->text('educacao_familiares')->nullable();
            $table->text('assistencia_social')->nullable();
            $table->text('assistencia_social_familiares')->nullable();
            $table->text('esporte_cultura_lazer')->nullable();
            $table->text('consideracoes_tecnicas')->nullable();
            $table->text('plano_acao')->nullable();
            $table->text('providencias_judiciario')->nullable();
            $table->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pias');
    }
};
