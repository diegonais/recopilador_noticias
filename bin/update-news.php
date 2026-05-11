<?php

declare(strict_types=1);

use PortalNoticias\Shared\Support\DateHelper;

$container = require __DIR__ . '/../bootstrap/app.php';

try {
    $result = $container->updateNewsUseCase()->execute();

    if (($result['message'] ?? null) !== null) {
        fwrite(
            STDOUT,
            formatCommandLog(
                $container->config()->timezone(),
                'INFO',
                (string) $result['message'],
            ) . PHP_EOL
        );
        exit(0);
    }

    fwrite(
        STDOUT,
        formatCommandLog(
            $container->config()->timezone(),
            'INFO',
            'Sincronizacion ABI finalizada.',
            [
                'status' => 'SUCCEEDED',
                'obtained' => (int) $result['obtained'],
                'new' => (int) $result['new'],
                'stored' => (int) $result['stored'],
            ],
        ) . PHP_EOL
    );
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        formatCommandLog(
            $container->config()->timezone(),
            'ERROR',
            'Sincronizacion ABI fallida.',
            [
                'status' => 'FAILED',
                'error' => $exception->getMessage(),
            ],
        ) . PHP_EOL
    );
    exit(1);
}

/**
 * @param array<string, mixed> $context
 */
function formatCommandLog(string $timezone, string $level, string $message, array $context = []): string
{
    $line = sprintf(
        '[%s] %s: %s',
        DateHelper::nowForConsoleLog($timezone),
        strtoupper($level),
        preg_replace('/\s+/', ' ', trim($message)) ?? trim($message),
    );

    if ($context === []) {
        return $line;
    }

    $encoded = json_encode(['context' => $context], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $line . ' ' . (is_string($encoded) ? $encoded : '{"context":{}}');
}

