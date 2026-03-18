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

    public function logUpdate(int $obtainedCount, int $newCount, ?string $error = null): void
    {
        $directory = dirname($this->config->logPath());

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $line = sprintf(
            '[%s] obtenidas=%d nuevas=%d',
            DateHelper::nowForLog($this->config->timezone()),
            $obtainedCount,
            $newCount,
        );

        if ($error !== null && trim($error) !== '') {
            $line .= ' error="' . TextHelper::normalizeWhitespace($error) . '"';
        }

        file_put_contents($this->config->logPath(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
