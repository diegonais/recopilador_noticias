<?php

declare(strict_types=1);

namespace PortalNoticias\News\Application;

use PortalNoticias\News\Infrastructure\Persistence\JsonNewsRepository;
use PortalNoticias\News\Infrastructure\Persistence\SupabaseNewsRepository;
use PortalNoticias\Shared\Config\AppConfig;
use RuntimeException;

final class MigrateNewsToSupabaseUseCase
{
    public function __construct(
        private readonly JsonNewsRepository $jsonRepository,
        private readonly SupabaseNewsRepository $supabaseRepository,
        private readonly NewsNormalizer $normalizer,
        private readonly AppConfig $config,
    ) {
    }

    public function execute(): int
    {
        if (!$this->config->isSupabaseEnabled()) {
            throw new RuntimeException('SUPABASE_ENABLED no esta activo.');
        }

        if (!$this->config->isSupabaseConfigured()) {
            throw new RuntimeException('Faltan variables de configuracion de Supabase.');
        }

        $localNews = $this->jsonRepository->findLatest(PHP_INT_MAX);

        if (count($localNews) === 0) {
            throw new RuntimeException('No se encontro un archivo local con noticias para migrar o esta vacio.');
        }

        $normalized = $this->normalizer->normalizeCollection($localNews);
        $this->supabaseRepository->saveAll($normalized);

        return count($normalized);
    }
}
