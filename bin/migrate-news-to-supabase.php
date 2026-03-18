<?php

declare(strict_types=1);

$container = require __DIR__ . '/../bootstrap/app.php';

try {
    $count = $container->migrateNewsToSupabaseUseCase()->execute();

    fwrite(
        STDOUT,
        sprintf('[OK] noticias_migradas=%d%s', $count, PHP_EOL)
    );
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
