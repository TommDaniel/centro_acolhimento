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

        // Maior número já usado no ano entre todos os documentos.
        $contador = 0;
        foreach (self::TABELAS as $tabela) {
            $max = (int) DB::table($tabela)
                ->whereYear('created_at', $ano)
                ->whereNotNull('numero_oficio')
                ->whereRaw('numero_oficio LIKE ?', ['%/'.$ano])
                ->max(DB::raw('CAST(SUBSTR(numero_oficio, 1, INSTR(numero_oficio, "/") - 1) AS INTEGER)'));

            if ($max > $contador) {
                $contador = $max;
            }
        }

        // Coleta todos os registros sem ofício, de todas as tabelas, ordenados por criação.
        $pendentes = collect();
        foreach (self::TABELAS as $tabela) {
            DB::table($tabela)
                ->whereNull('numero_oficio')
                ->orderBy('created_at')
                ->get(['id', 'created_at'])
                ->each(fn ($r) => $pendentes->push(['tabela' => $tabela, 'id' => $r->id, 'created_at' => $r->created_at]));
        }

        $pendentes = $pendentes->sortBy('created_at')->values();

        foreach ($pendentes as $item) {
            $contador++;
            DB::table($item['tabela'])->where('id', $item['id'])->update([
                'numero_oficio' => $contador.'/'.$ano,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversível de forma segura — não há como saber quais eram nulos.
    }
};
