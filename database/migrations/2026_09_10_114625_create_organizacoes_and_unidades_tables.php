<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('context_slot')->default(1)->unique();
            $table->string('codigo', 64)->unique();
            $table->string('nome');
            $table->timestampsTz();
        });

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('context_slot')->default(1)->unique();
            $table->foreignId('organizacao_id')->index()->constrained('organizacoes')->restrictOnDelete();
            $table->string('codigo', 64)->unique();
            $table->string('nome');
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE organizacoes ADD CONSTRAINT organizacoes_single_context_check CHECK (context_slot = 1)');
            DB::statement('ALTER TABLE unidades ADD CONSTRAINT unidades_single_context_check CHECK (context_slot = 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('organizacoes');
    }
};
