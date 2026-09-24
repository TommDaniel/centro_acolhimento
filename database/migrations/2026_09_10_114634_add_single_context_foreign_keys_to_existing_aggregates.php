<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const UNIT_TABLES = [
        'users',
        'setores',
        'pias',
        'visitas_tecnicas',
        'reports',
        'pertences',
        'eventos',
    ];

    /** @var list<string> */
    private const SECTOR_AGGREGATES = [
        'users',
        'pias',
        'visitas_tecnicas',
        'reports',
        'pertences',
        'eventos',
    ];

    public function up(): void
    {
        Schema::table('criancas', function (Blueprint $table) {
            $table->foreignId('organizacao_id')->nullable()->index()->constrained('organizacoes')->restrictOnDelete();
        });

        foreach (self::UNIT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('unidade_id')->nullable()->index()->constrained('unidades')->restrictOnDelete();
            });
        }

        Schema::table('setores', function (Blueprint $table) {
            $table->unique(['id', 'unidade_id'], 'setores_id_unidade_unique');
        });

        foreach (self::SECTOR_AGGREGATES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->index(['setor_id', 'unidade_id'], $tableName.'_setor_unidade_index');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            foreach (self::SECTOR_AGGREGATES as $tableName) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (setor_id, unidade_id) REFERENCES setores (id, unidade_id)',
                    $tableName,
                    $tableName.'_setor_unidade_foreign',
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (array_reverse(self::SECTOR_AGGREGATES) as $tableName) {
                DB::statement(sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                    $tableName,
                    $tableName.'_setor_unidade_foreign',
                ));
            }
        }

        foreach (array_reverse(self::SECTOR_AGGREGATES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex($tableName.'_setor_unidade_index');
            });
        }

        Schema::table('setores', function (Blueprint $table) {
            $table->dropUnique('setores_id_unidade_unique');
        });

        foreach (array_reverse(self::UNIT_TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('unidade_id');
            });
        }

        Schema::table('criancas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizacao_id');
        });
    }
};
