<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const INSTANT_COLUMNS = [
        'users' => ['email_verified_at', 'created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'failed_jobs' => ['failed_at'],
        'setores' => ['created_at', 'updated_at'],
        'criancas' => ['created_at', 'updated_at'],
        'crianca_documentos' => ['created_at', 'updated_at'],
        'pias' => ['created_at', 'updated_at'],
        'visitas_tecnicas' => ['created_at', 'updated_at'],
        'reports' => ['created_at', 'updated_at'],
        'pertences' => ['created_at', 'updated_at'],
        'familiares' => ['created_at', 'updated_at'],
        'eventos' => ['inicio', 'fim', 'created_at', 'updated_at'],
        'pia_anexos' => ['created_at', 'updated_at'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->alterColumns('TIMESTAMPTZ(0)', "AT TIME ZONE 'UTC'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->alterColumns('TIMESTAMP(0) WITHOUT TIME ZONE', "AT TIME ZONE 'UTC'");
    }

    private function alterColumns(string $targetType, string $conversion): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::INSTANT_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE %s USING "%s" %s',
                    $table,
                    $column,
                    $targetType,
                    $column,
                    $conversion,
                ));
            }
        }
    }
};
