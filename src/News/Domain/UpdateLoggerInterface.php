<?php

declare(strict_types=1);

namespace PortalNoticias\News\Domain;

interface UpdateLoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function logMessage(string $level, string $message, array $context = []): void;

    public function logUpdate(int $obtainedCount, int $newCount, ?string $error = null): void;
}
