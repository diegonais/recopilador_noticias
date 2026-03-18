<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Persistence;

use PortalNoticias\News\Domain\NewsRepositoryInterface;
use RuntimeException;
use Throwable;

final class SynchronizedNewsRepository implements NewsRepositoryInterface
{
    public function __construct(
        private readonly NewsRepositoryInterface $primaryRepository,
        private readonly NewsRepositoryInterface $fallbackRepository,
    ) {
    }

    public function findLatest(int $limit): array
    {
        try {
            return $this->primaryRepository->findLatest($limit);
        } catch (Throwable) {
            return $this->fallbackRepository->findLatest($limit);
        }
    }

    public function saveAll(array $items): void
    {
        $errors = [];

        try {
            $this->primaryRepository->saveAll($items);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        try {
            $this->fallbackRepository->saveAll($items);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(' | ', $errors));
        }
    }

    public function latestUpdatedAt(): ?string
    {
        try {
            $updatedAt = $this->primaryRepository->latestUpdatedAt();

            if ($updatedAt !== null) {
                return $updatedAt;
            }
        } catch (Throwable) {
        }

        return $this->fallbackRepository->latestUpdatedAt();
    }
}
