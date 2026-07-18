<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crianca_id')->constrained('criancas')->cascadeOnDelete();
            $table->json('itens'); // [{descricao, quantidade}]
            $table->date('data_entrega');
            $table->string('assinatura_entrega')->nullable();
            $table->boolean('devolvido')->default(false);
            $table->date('data_devolucao')->nullable();
            $table->string('assinatura_devolucao')->nullable();
            $table->text('observacao_devolucao')->nullable();
            $table->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pertences');
    }
};
