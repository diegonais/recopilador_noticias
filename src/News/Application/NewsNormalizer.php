<?php

declare(strict_types=1);

namespace PortalNoticias\News\Application;

use PortalNoticias\News\Domain\NewsItem;
use PortalNoticias\Shared\Support\DateHelper;

final class NewsNormalizer
{
    public function __construct(private readonly string $timezone)
    {
    }

    /**
     * @param array<int, array<string, mixed>|NewsItem> $items
     *
     * @return array<int, NewsItem>
     */
    public function normalizeCollection(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if ($item instanceof NewsItem) {
                $normalized[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $validated = NewsItem::fromArray($item, $this->timezone);

            if ($validated !== null) {
                $normalized[] = $validated;
            }
        }

        return $this->deduplicateAndSort($normalized);
    }

    /**
     * @param array<int, array<string, mixed>|NewsItem> $existingNews
     * @param array<int, array<string, mixed>|NewsItem> $incomingNews
     *
     * @return array<int, NewsItem>
     */
    public function mergeNews(array $existingNews, array $incomingNews, int $limit): array
    {
        $merged = array_merge(
            $this->normalizeCollection($existingNews),
            $this->normalizeCollection($incomingNews),
        );

        return array_slice($this->deduplicateAndSort($merged), 0, max(1, $limit));
    }

    /**
     * @param array<int, array<string, mixed>|NewsItem> $existingNews
     * @param array<int, array<string, mixed>|NewsItem> $incomingNews
     */
    public function countNewItems(array $existingNews, array $incomingNews): int
    {
        $existingMap = [];

        foreach ($this->normalizeCollection($existingNews) as $item) {
            $existingMap[$item->uniqueKey()] = true;
        }

        $count = 0;

        foreach ($this->normalizeCollection($incomingNews) as $item) {
            $key = $item->uniqueKey();

            if (!isset($existingMap[$key])) {
                $existingMap[$key] = true;
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<int, NewsItem> $items
     *
     * @return array<int, NewsItem>
     */
    private function deduplicateAndSort(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $unique[$item->uniqueKey()] = $item;
        }

        $result = array_values($unique);

        usort($result, function (NewsItem $left, NewsItem $right): int {
            return DateHelper::toTimestamp($right->publishedAt(), $this->timezone)
                <=> DateHelper::toTimestamp($left->publishedAt(), $this->timezone);
        });

        return $result;
    }
}
