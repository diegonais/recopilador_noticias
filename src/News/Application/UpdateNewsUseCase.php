<?php

declare(strict_types=1);

namespace PortalNoticias\News\Application;

use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\News\Domain\NewsSourceInterface;
use PortalNoticias\News\Domain\UpdateLoggerInterface;
use PortalNoticias\Shared\Config\AppConfig;
use RuntimeException;
use Throwable;

final class UpdateNewsUseCase
{
    public function __construct(
        private readonly NewsSourceInterface $source,
        private readonly NewsRepositoryInterface $repository,
        private readonly NewsNormalizer $normalizer,
        private readonly UpdateLoggerInterface $logger,
        private readonly AppConfig $config,
    ) {
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function execute(): array
    {
        $existingNews = $this->repository->findLatest($this->config->maxNewsItems());

        try {
            $fetchedItems = $this->source->fetchLatest();
        } catch (Throwable $exception) {
            $this->logger->logUpdate(0, 0, $exception->getMessage());
            throw $exception;
        }

        $processedIncoming = $this->normalizer->normalizeCollection($fetchedItems);

        if (count($fetchedItems) > 0 && count($processedIncoming) === 0) {
            $message = 'El feed respondio, pero ninguna noticia paso la validacion. Se conservo el archivo existente.';
            $this->logger->logUpdate(count($fetchedItems), 0, $message);

            throw new RuntimeException($message);
        }

        if (count($fetchedItems) === 0 && count($existingNews) > 0) {
            $message = 'El feed respondio sin noticias. Se conservo el archivo existente para evitar sobrescribirlo con vacio.';
            $this->logger->logUpdate(0, 0, $message);

            return [
                'success' => true,
                'obtained' => 0,
                'new' => 0,
                'stored' => count($existingNews),
                'message' => $message,
            ];
        }

        $newCount = $this->normalizer->countNewItems($existingNews, $processedIncoming);
        $finalNews = $this->normalizer->mergeNews(
            $existingNews,
            $processedIncoming,
            $this->config->maxNewsItems(),
        );

        try {
            $this->repository->saveAll($finalNews);
        } catch (Throwable $exception) {
            $this->logger->logUpdate(count($fetchedItems), 0, $exception->getMessage());
            throw $exception;
        }

        $this->logger->logUpdate(count($fetchedItems), $newCount);

        return [
            'success' => true,
            'obtained' => count($fetchedItems),
            'new' => $newCount,
            'stored' => count($finalNews),
            'message' => null,
        ];
    }
}

