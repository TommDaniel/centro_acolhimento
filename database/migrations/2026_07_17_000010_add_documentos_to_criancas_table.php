<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criancas', function (Blueprint $table) {
            $table->string('identidade_genero')->nullable()->after('sexo');
            $table->string('rn')->nullable()->after('certidao_nascimento');
            $table->string('cartao_sus')->nullable()->after('rn');
            $table->string('nis')->nullable()->after('cartao_sus');
            $table->string('titulo_eleitor')->nullable()->after('nis');
        });
    }

    public function down(): void
    {
        Schema::table('criancas', function (Blueprint $table) {
            $table->dropColumn(['identidade_genero', 'rn', 'cartao_sus', 'nis', 'titulo_eleitor']);
        });
    }
};
