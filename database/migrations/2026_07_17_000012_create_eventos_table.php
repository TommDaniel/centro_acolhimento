<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('tipo')->default('tarefa'); // visita | audiencia | atendimento | tarefa | outro
            $table->text('descricao')->nullable();
            $table->dateTime('inicio');
            $table->dateTime('fim')->nullable();
            $table->boolean('dia_inteiro')->default(false);
            $table->boolean('concluido')->default(false);
            $table->foreignId('crianca_id')->nullable()->constrained('criancas')->nullOnDelete();
            $table->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
