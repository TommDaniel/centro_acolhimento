<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABELAS = ['pias', 'visitas_tecnicas', 'reports', 'pertences'];

    public function up(): void
    {
        $ano = now()->year;

        // Coleta todos os registros do ano, de todas as tabelas, ordenados por criação.
        $todos = collect();
        foreach (self::TABELAS as $tabela) {
            DB::table($tabela)
                ->whereYear('created_at', $ano)
                ->orderBy('created_at')
                ->get(['id', 'created_at'])
                ->each(fn ($r) => $todos->push(['tabela' => $tabela, 'id' => $r->id, 'created_at' => $r->created_at]));
        }

        $todos = $todos->sortBy('created_at')->values();

        foreach ($todos as $indice => $item) {
            DB::table($item['tabela'])->where('id', $item['id'])->update([
                'numero_oficio' => ($indice + 1).'/'.$ano,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversível de forma segura.
    }
};
