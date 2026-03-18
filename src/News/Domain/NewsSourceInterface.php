<?php

declare(strict_types=1);

namespace PortalNoticias\News\Domain;

interface NewsSourceInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchLatest(): array;
}
