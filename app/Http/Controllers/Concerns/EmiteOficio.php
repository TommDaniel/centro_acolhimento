<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait EmiteOficio
{
    public const LOCAL_OFICIO = 'Santo Ângelo';

    /** @var list<string> */
    private const TABELAS_DOCUMENTOS = ['pias', 'visitas_tecnicas', 'reports', 'pertences'];

    /**
     * Retorna o número do ofício informado ou gera um sequencial único por ano
     * entre todos os tipos de documento.
     */
    protected function numeroOficio(Request $request, string $tabela): ?string
    {
        $numero = $request->input('numero_oficio');

        if (filled($numero)) {
            return $numero;
        }

        $ano = now()->year;
        $ultimo = $this->maiorNumeroOficioAno($ano);

        return ($ultimo + 1).'/'.$ano;
    }

    /**
     * Maior número de ofício já usado no ano, considerando todas as tabelas.
     */
    protected function maiorNumeroOficioAno(int $ano): int
    {
        $maximos = collect(self::TABELAS_DOCUMENTOS)
            ->map(fn (string $tabela) => DB::table($tabela)
                ->whereYear('created_at', $ano)
                ->whereNotNull('numero_oficio')
                ->whereRaw('numero_oficio LIKE ?', ['%/'.$ano])
                ->get()
                ->map(fn ($r) => (int) explode('/', (string) $r->numero_oficio)[0])
                ->max());

        return $maximos->max() ?? 0;
    }

    /**
     * Regras de validação para o campo número do ofício.
     */
    protected function regraNumeroOficio(): array
    {
        return ['nullable', 'string', 'max:50'];
    }
}
