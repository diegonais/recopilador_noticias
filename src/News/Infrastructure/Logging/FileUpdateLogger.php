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
            '[%s] [%s] %s',
            DateHelper::nowForLog($this->config->timezone()),
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
        $line = sprintf(
            '[%s] [%s] resumen obtenidas=%d nuevas=%d',
            DateHelper::nowForLog($this->config->timezone()),
            $error !== null && trim($error) !== '' ? 'ERROR' : 'OK',
            $obtainedCount,
            $newCount,
        );

        if ($error !== null && trim($error) !== '') {
            $line .= ' error="' . TextHelper::normalizeWhitespace($error) . '"';
        }

        $this->writeLine($line);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string
    {
        $parts = [];

        foreach ($context as $key => $value) {
            $parts[] = $key . '=' . $this->formatContextValue($value);
        }

        return implode(' ', $parts);
    }

    private function formatContextValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $value = is_string($encoded) ? $encoded : 'array';
        }

        $text = TextHelper::normalizeWhitespace((string) $value);

        if (strlen($text) > 300) {
            $text = substr($text, 0, 297) . '...';
        }

        return '"' . str_replace('"', '\"', $text) . '"';
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
