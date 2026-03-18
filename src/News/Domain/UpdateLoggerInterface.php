<?php

declare(strict_types=1);

namespace PortalNoticias\News\Domain;

interface UpdateLoggerInterface
{
    public function logUpdate(int $obtainedCount, int $newCount, ?string $error = null): void;
}
