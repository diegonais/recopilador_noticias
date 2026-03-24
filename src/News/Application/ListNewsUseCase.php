<?php

declare(strict_types=1);

namespace PortalNoticias\News\Application;

use DateTimeImmutable;
use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\News\Domain\NewsItem;
use Throwable;

final class ListNewsUseCase
{
    public function __construct(private readonly NewsRepositoryInterface $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $limit, ?int $year = null, ?int $month = null, ?int $day = null): array
    {
        if ($this->isFutureFilter($year, $month, $day)) {
            return [
                'success' => true,
                'count' => 0,
                'updated_at' => $this->repository->latestUpdatedAt(),
                'data' => [],
            ];
        }

        $hasDateFilter = $year !== null || $month !== null || $day !== null;
        $queryLimit = $hasDateFilter ? 0 : $limit;

        $news = $this->repository->findLatest($queryLimit);

        if ($hasDateFilter) {
            $news = array_values(array_filter(
                $news,
                fn (NewsItem $item): bool => $this->matchesDateFilter($item, $year, $month, $day),
            ));
        }

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

    private function isFutureFilter(?int $year, ?int $month, ?int $day): bool
    {
        if ($year === null) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        $todayYear = (int) $today->format('Y');

        if ($year > $todayYear) {
            return true;
        }

        if ($year < $todayYear || $month === null) {
            return false;
        }

        $todayMonth = (int) $today->format('n');

        if ($month > $todayMonth) {
            return true;
        }

        if ($month < $todayMonth || $day === null) {
            return false;
        }

        $todayDay = (int) $today->format('j');

        return $day > $todayDay;
    }
    private function matchesDateFilter(NewsItem $item, ?int $year, ?int $month, ?int $day): bool
    {
        try {
            $date = new DateTimeImmutable($item->publishedAt());
        } catch (Throwable) {
            return false;
        }

        if ($year !== null && (int) $date->format('Y') !== $year) {
            return false;
        }

        if ($month !== null && (int) $date->format('n') !== $month) {
            return false;
        }

        if ($day !== null && (int) $date->format('j') !== $day) {
            return false;
        }

        return true;
    }
}
