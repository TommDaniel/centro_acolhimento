<?php

declare(strict_types=1);

if (! function_exists('dataPorExtensoPtBr')) {
    /**
     * Formata uma data no padrão brasileiro por extenso.
     * Exemplo: "07 de julho de 2026".
     */
    function dataPorExtensoPtBr(\DateTimeInterface|string|null $data, bool $capitalizar = false): ?string
    {
        if ($data === null) {
            return null;
        }

        $carbon = $data instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::instance($data)
            : \Illuminate\Support\Carbon::parse($data);

        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];

        $texto = sprintf(
            '%02d de %s de %d',
            $carbon->day,
            $meses[$carbon->month],
            $carbon->year
        );

        return $capitalizar ? ucfirst($texto) : $texto;
    }
}
