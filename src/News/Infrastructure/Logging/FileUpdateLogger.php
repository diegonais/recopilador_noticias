<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Logging;

use PortalNoticias\News\Domain\UpdateLoggerInterface;
use PortalNoticias\Shared\Config\AppConfig;
use PortalNoticias\Shared\Support\DateHelper;
use PortalNoticias\Shared\Support\TextHelper;

final class FileUpdateLogger implements UpdateLoggerInterface
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logMessage(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            '[%s] %s: %s',
            DateHelper::nowForConsoleLog($this->config->timezone()),
            strtoupper($level),
            TextHelper::normalizeWhitespace($message),
        );

        if ($context !== []) {
            $line .= ' ' . $this->formatContext($context);
        }

        $this->writeLine($line);
    }

    public function logUpdate(int $obtainedCount, int $newCount, ?string $error = null): void
    {
        $context = [
            'status' => $error !== null && trim($error) !== '' ? 'FAILED' : 'SUCCEEDED',
            'obtained' => $obtainedCount,
            'new' => $newCount,
        ];

        if ($error !== null && trim($error) !== '') {
            $context['error'] = $error;
        }

        $line = sprintf(
            '[%s] %s: Resumen de sincronizacion ABI %s',
            DateHelper::nowForConsoleLog($this->config->timezone()),
            $error !== null && trim($error) !== '' ? 'ERROR' : 'INFO',
            $this->formatContext($context),
        );

        $this->writeLine($line);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string
    {
        $encoded = json_encode(
            ['context' => $this->normalizeContext($context)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return is_string($encoded) ? $encoded : '{"context":{}}';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = $this->normalizeContextValue($value);
        }

        return $normalized;
    }

    private function normalizeContextValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->normalizeContext($value);
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = TextHelper::normalizeWhitespace((string) $value);

        if (strlen($text) > 300) {
            $text = substr($text, 0, 297) . '...';
        }

        return $text;
    }

    private function writeLine(string $line): void
    {
        $directory = dirname($this->config->logPath());

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->config->logPath(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
