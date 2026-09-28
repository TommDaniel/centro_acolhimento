<?php

declare(strict_types=1);

function publicStorageEntryExists(string $projectRoot): bool
{
    $publicStorage = rtrim($projectRoot, DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'storage';

    return is_link($publicStorage) || file_exists($publicStorage);
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $projectRoot = $argv[1] ?? dirname(__DIR__);

    if (publicStorageEntryExists($projectRoot)) {
        fwrite(
            STDERR,
            "Inicialização bloqueada: public/storage existe e pode expor arquivos privados ou legados. Não remova o link nem os arquivos sem inventário e cópia verificada; realoque a entrada de forma controlada antes de iniciar a demonstração.\n",
        );

        exit(1);
    }

    exit(0);
}
