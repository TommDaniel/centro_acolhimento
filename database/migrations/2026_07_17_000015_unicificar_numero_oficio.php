<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABELAS = ['pias', 'visitas_tecnicas', 'reports', 'pertences'];

    public function up(): void
    {
        $documentos = collect();

        foreach (self::TABELAS as $tabela) {
            DB::table($tabela)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'created_at', 'numero_oficio'])
                ->each(fn (object $documento) => $documentos->push([
                    'tabela' => $tabela,
                    'id' => $documento->id,
                    'created_at' => (string) $documento->created_at,
                    'numero_oficio' => $documento->numero_oficio,
                ]));
        }

        $documentos = $documentos
            ->sortBy(fn (array $documento): string => implode('|', [
                $documento['created_at'],
                $documento['tabela'],
                str_pad((string) $documento['id'], 20, '0', STR_PAD_LEFT),
            ]))
            ->values();

        $numerosEncontrados = [];

        foreach ($documentos as $documento) {
            $numero = $documento['numero_oficio'];

            if (! is_string($numero) || preg_match('/^(?<sequencial>[1-9][0-9]*)\/(?<ano>[0-9]{4})$/', $numero, $partes) !== 1) {
                throw new RuntimeException("Número de ofício inválido em {$documento['tabela']}#{$documento['id']}.");
            }

            if ($partes['ano'] !== substr($documento['created_at'], 0, 4)) {
                throw new RuntimeException("Ano do número de ofício inválido em {$documento['tabela']}#{$documento['id']}.");
            }

            if (isset($numerosEncontrados[$numero])) {
                throw new RuntimeException("Número de ofício duplicado em {$documento['tabela']}#{$documento['id']}.");
            }

            $numerosEncontrados[$numero] = true;
        }
    }

    public function down(): void
    {
        // Validação somente leitura: não há dados a reverter.
    }
};
