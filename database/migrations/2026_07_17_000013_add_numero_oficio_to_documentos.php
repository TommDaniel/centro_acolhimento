<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pias', 'visitas_tecnicas', 'reports', 'pertences'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('numero_oficio')->nullable()->after('setor_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['pias', 'visitas_tecnicas', 'reports', 'pertences'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('numero_oficio');
            });
        }
    }
};
