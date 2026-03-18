<?php

declare(strict_types=1);

namespace PortalNoticias\News\Application;

use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\News\Domain\NewsItem;

final class ListNewsUseCase
{
    public function __construct(private readonly NewsRepositoryInterface $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $limit): array
    {
        $news = $this->repository->findLatest($limit);

        return [
            'success' => true,
            'count' => count($news),
            'updated_at' => $this->repository->latestUpdatedAt(),
            'data' => array_map(
                static fn (NewsItem $item): array => $item->toArray(),
                $news,
            ),
        ];
    }
}
