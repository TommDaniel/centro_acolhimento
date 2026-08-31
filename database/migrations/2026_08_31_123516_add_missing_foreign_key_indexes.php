<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const INDEXES = [
        'users' => [
            'setor_id' => 'users_setor_id_idx',
        ],
        'criancas' => [
            'created_by' => 'criancas_created_by_idx',
            'updated_by' => 'criancas_updated_by_idx',
        ],
        'crianca_documentos' => [
            'crianca_id' => 'crianca_documentos_crianca_id_idx',
            'uploaded_by' => 'crianca_documentos_uploaded_by_idx',
        ],
        'pias' => [
            'crianca_id' => 'pias_crianca_id_idx',
            'setor_id' => 'pias_setor_id_idx',
            'created_by' => 'pias_created_by_idx',
        ],
        'visitas_tecnicas' => [
            'crianca_id' => 'visitas_tecnicas_crianca_id_idx',
            'setor_id' => 'visitas_tecnicas_setor_id_idx',
            'created_by' => 'visitas_tecnicas_created_by_idx',
        ],
        'reports' => [
            'crianca_id' => 'reports_crianca_id_idx',
            'setor_id' => 'reports_setor_id_idx',
            'created_by' => 'reports_created_by_idx',
        ],
        'pertences' => [
            'crianca_id' => 'pertences_crianca_id_idx',
            'setor_id' => 'pertences_setor_id_idx',
            'created_by' => 'pertences_created_by_idx',
        ],
        'familiares' => [
            'crianca_id' => 'familiares_crianca_id_idx',
            'created_by' => 'familiares_created_by_idx',
        ],
        'eventos' => [
            'crianca_id' => 'eventos_crianca_id_idx',
            'setor_id' => 'eventos_setor_id_idx',
            'created_by' => 'eventos_created_by_idx',
        ],
        'pia_anexos' => [
            'pia_id' => 'pia_anexos_pia_id_idx',
            'uploaded_by' => 'pia_anexos_uploaded_by_idx',
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $column => $indexName) {
                    $table->index($column, $indexName);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES, true) as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach (array_reverse($indexes) as $indexName) {
                    $table->dropIndex($indexName);
                }
            });
        }
    }
};
