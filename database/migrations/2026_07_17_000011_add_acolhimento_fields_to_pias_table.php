<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pias', function (Blueprint $table) {
            $table->boolean('acolhimento_anterior')->default(false)->after('dados_acolhimento');
            $table->text('acolhimento_anterior_detalhes')->nullable()->after('acolhimento_anterior');
            $table->string('encaminhado_por')->nullable()->after('acolhimento_anterior_detalhes');
        });
    }

    public function down(): void
    {
        Schema::table('pias', function (Blueprint $table) {
            $table->dropColumn(['acolhimento_anterior', 'acolhimento_anterior_detalhes', 'encaminhado_por']);
        });
    }
};
