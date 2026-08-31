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

        $contador = 0;
        foreach (self::TABELAS as $tabela) {
            $maiorNumeroDaTabela = DB::table($tabela)
                ->whereYear('created_at', $ano)
                ->whereNotNull('numero_oficio')
                ->pluck('numero_oficio')
                ->map(static function (string $numeroOficio) use ($ano): int {
                    $correspondeAoFormato = preg_match(
                        '/^(?<sequencial>[1-9][0-9]*)\/(?<ano>[0-9]{4})$/',
                        $numeroOficio,
                        $partes,
                    );

                    if ($correspondeAoFormato !== 1 || (int) $partes['ano'] !== $ano) {
                        return 0;
                    }

                    return (int) $partes['sequencial'];
                })
                ->max() ?? 0;

            $contador = max($contador, $maiorNumeroDaTabela);
        }

        $pendentes = collect();
        foreach (self::TABELAS as $tabela) {
            DB::table($tabela)
                ->whereNull('numero_oficio')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'created_at'])
                ->each(fn ($registro) => $pendentes->push([
                    'tabela' => $tabela,
                    'id' => (int) $registro->id,
                    'created_at' => (string) $registro->created_at,
                ]));
        }

        $pendentes = $pendentes
            ->sort(static fn (array $primeiro, array $segundo): int => [
                $primeiro['created_at'],
                $primeiro['tabela'],
                $primeiro['id'],
            ] <=> [
                $segundo['created_at'],
                $segundo['tabela'],
                $segundo['id'],
            ])
            ->values();

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
