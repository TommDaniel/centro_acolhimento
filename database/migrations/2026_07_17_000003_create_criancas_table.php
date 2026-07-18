<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criancas', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo');
            $table->string('nome_social')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('sexo')->nullable();
            $table->string('cor_raca')->nullable();
            $table->string('naturalidade')->nullable();
            $table->string('nacionalidade')->nullable();
            $table->string('rg')->nullable();
            $table->string('cpf')->nullable();
            $table->string('certidao_nascimento')->nullable();
            $table->string('nome_mae')->nullable();
            $table->string('nome_pai')->nullable();
            $table->string('responsavel_legal')->nullable();
            $table->string('contato_responsavel')->nullable();
            $table->string('endereco_familia')->nullable();
            $table->string('processo_numero')->nullable()->index();
            $table->string('vara')->nullable();
            $table->string('comarca')->nullable();
            $table->date('data_acolhimento')->nullable();
            $table->text('motivo_acolhimento')->nullable();
            $table->string('foto')->nullable();
            $table->string('status')->default('acolhida'); // acolhida | desligada
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criancas');
    }
};
