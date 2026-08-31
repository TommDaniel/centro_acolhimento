<?php

declare(strict_types=1);

if (! filter_var(getenv('VERCEL'), FILTER_VALIDATE_BOOL)) {
    fwrite(STDERR, "Este reset existe somente para o build efêmero da demonstração sintética na Vercel.\n");

    exit(1);
}

$databasePath = dirname(__DIR__).'/database/database.sqlite';
if (! file_exists($databasePath)) {
    touch($databasePath);
}

foreach ([
    'VERCEL' => '0',
    'APP_ENV' => 'local',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $databasePath,
    'DB_URL' => '',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$command = escapeshellarg(PHP_BINARY).' artisan migrate:fresh --seed --force --ansi';
passthru($command, $exitCode);

exit($exitCode);
