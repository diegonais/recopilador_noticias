<?php

declare(strict_types=1);

use PortalNoticias\Shared\Support\DateHelper;

$container = require __DIR__ . '/../bootstrap/app.php';

try {
    $result = $container->updateNewsUseCase()->execute();

    if (($result['message'] ?? null) !== null) {
        fwrite(STDOUT, '[INFO] ' . $result['message'] . PHP_EOL);
        exit(0);
    }

    fwrite(
        STDOUT,
        sprintf(
            '[OK] %s | obtenidas=%d | nuevas=%d | almacenadas=%d%s',
            DateHelper::nowForLog($container->config()->timezone()),
            (int) $result['obtained'],
            (int) $result['new'],
            (int) $result['stored'],
            PHP_EOL,
        )
    );
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

