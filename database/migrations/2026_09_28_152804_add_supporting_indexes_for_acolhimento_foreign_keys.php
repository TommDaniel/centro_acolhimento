<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acolhimentos', function (Blueprint $table): void {
            $table->index(
                ['encerrado_por_movimentacao_id', 'id'],
                'acolhimentos_closing_movement_episode_index',
            );
        });

        Schema::table('pias', function (Blueprint $table): void {
            $table->index(
                ['acolhimento_id', 'crianca_id'],
                'pias_acolhimento_child_index',
            );
            $table->dropIndex('pias_acolhimento_index');
        });
    }

    public function down(): void
    {
        Schema::table('pias', function (Blueprint $table): void {
            $table->index('acolhimento_id', 'pias_acolhimento_index');
            $table->dropIndex('pias_acolhimento_child_index');
        });

        Schema::table('acolhimentos', function (Blueprint $table): void {
            $table->dropIndex('acolhimentos_closing_movement_episode_index');
        });
    }
};
