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
        $this->logger->logMessage('INFO', 'Inicio de sincronizacion de noticias ABI.', [
            'rss_url' => $this->config->abiRssUrl(),
            'max_items' => $this->config->maxNewsItems(),
            'supabase_enabled' => $this->config->isSupabaseEnabled(),
            'supabase_configured' => $this->config->isSupabaseConfigured(),
        ]);

        try {
            $existingNews = $this->repository->findLatest($this->config->maxNewsItems());
            $this->logger->logMessage('INFO', 'Lectura de noticias existentes completada.', [
                'existentes' => count($existingNews),
            ]);
        } catch (Throwable $exception) {
            $this->logger->logMessage('ERROR', 'No se pudo leer el repositorio de noticias existente.', [
                'error' => $exception->getMessage(),
            ]);
            $this->logger->logUpdate(0, 0, $exception->getMessage());

            throw $exception;
        }

        try {
            $this->logger->logMessage('INFO', 'Solicitando feed RSS de ABI.');
            $fetchedItems = $this->source->fetchLatest();
            $this->logger->logMessage('INFO', 'Feed RSS de ABI recibido correctamente.', [
                'obtenidas' => count($fetchedItems),
            ]);
        } catch (Throwable $exception) {
            $this->logger->logMessage('ERROR', 'Fallo la lectura del feed RSS de ABI.', [
                'error' => $exception->getMessage(),
            ]);
            $this->logger->logUpdate(0, 0, $exception->getMessage());
            throw $exception;
        }

        $processedIncoming = $this->normalizer->normalizeCollection($fetchedItems);
        $this->logger->logMessage('INFO', 'Noticias del feed normalizadas.', [
            'obtenidas' => count($fetchedItems),
            'validas' => count($processedIncoming),
        ]);

        if (count($fetchedItems) > 0 && count($processedIncoming) === 0) {
            $message = 'El feed respondio, pero ninguna noticia paso la validacion. Se conservo el archivo existente.';
            $this->logger->logMessage('ERROR', $message);
            $this->logger->logUpdate(count($fetchedItems), 0, $message);

            throw new RuntimeException($message);
        }

        if (count($fetchedItems) === 0 && count($existingNews) > 0) {
            $message = 'El feed respondio sin noticias. Se conservo el archivo existente para evitar sobrescribirlo con vacio.';
            $this->logger->logMessage('WARN', $message, [
                'existentes' => count($existingNews),
            ]);
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
            $this->logger->logMessage('INFO', 'Guardando noticias sincronizadas.', [
                'total_a_guardar' => count($finalNews),
                'nuevas' => $newCount,
            ]);
            $this->repository->saveAll($finalNews);
            $this->logger->logMessage('INFO', 'Guardado de noticias completado.', [
                'almacenadas' => count($finalNews),
            ]);
        } catch (Throwable $exception) {
            $this->logger->logMessage('ERROR', 'No se pudo guardar la sincronizacion de noticias.', [
                'error' => $exception->getMessage(),
            ]);
            $this->logger->logUpdate(count($fetchedItems), 0, $exception->getMessage());
            throw $exception;
        }

        $this->logger->logUpdate(count($fetchedItems), $newCount);
        $this->logger->logMessage('INFO', 'Fin de sincronizacion de noticias ABI.', [
            'obtenidas' => count($fetchedItems),
            'nuevas' => $newCount,
            'almacenadas' => count($finalNews),
        ]);

        return [
            'success' => true,
            'obtained' => count($fetchedItems),
            'new' => $newCount,
            'stored' => count($finalNews),
            'message' => null,
        ];
    }
}

