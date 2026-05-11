<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Infrastructure\Container;

use PortalNoticias\News\Application\ListNewsUseCase;
use PortalNoticias\News\Application\MigrateNewsToSupabaseUseCase;
use PortalNoticias\News\Application\NewsNormalizer;
use PortalNoticias\News\Application\UpdateNewsUseCase;
use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\News\Infrastructure\Abi\AbiFeedClient;
use PortalNoticias\News\Infrastructure\Abi\ArticleContentExtractor;
use PortalNoticias\News\Infrastructure\Abi\ArticlePageCache;
use PortalNoticias\News\Infrastructure\Logging\FileUpdateLogger;
use PortalNoticias\News\Infrastructure\Persistence\JsonNewsRepository;
use PortalNoticias\News\Infrastructure\Persistence\SupabaseNewsRepository;
use PortalNoticias\News\Infrastructure\Persistence\SynchronizedNewsRepository;
use PortalNoticias\Shared\Config\AppConfig;
use PortalNoticias\Shared\Http\HttpClientInterface;
use PortalNoticias\Shared\Infrastructure\Http\SimpleHttpClient;

final class ApplicationContainer
{
    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    public function __construct(private readonly AppConfig $config)
    {
    }

    public function config(): AppConfig
    {
        return $this->config;
    }

    public function httpClient(): HttpClientInterface
    {
        return $this->singleton('http_client', static fn (): HttpClientInterface => new SimpleHttpClient());
    }

    public function newsNormalizer(): NewsNormalizer
    {
        return $this->singleton(
            'news_normalizer',
            fn (): NewsNormalizer => new NewsNormalizer($this->config->timezone())
        );
    }

    public function jsonNewsRepository(): JsonNewsRepository
    {
        return $this->singleton(
            'json_news_repository',
            fn (): JsonNewsRepository => new JsonNewsRepository($this->config)
        );
    }

    public function supabaseNewsRepository(): SupabaseNewsRepository
    {
        return $this->singleton(
            'supabase_news_repository',
            fn (): SupabaseNewsRepository => new SupabaseNewsRepository($this->config, $this->httpClient())
        );
    }

    public function newsRepository(): NewsRepositoryInterface
    {
        return $this->singleton('news_repository', function (): NewsRepositoryInterface {
            $localRepository = $this->jsonNewsRepository();

            if (!$this->config->isSupabaseConfigured()) {
                return $localRepository;
            }

            return new SynchronizedNewsRepository(
                $this->supabaseNewsRepository(),
                $localRepository,
            );
        });
    }

    public function updateNewsRepository(): NewsRepositoryInterface
    {
        return $this->singleton('update_news_repository', function (): NewsRepositoryInterface {
            $localRepository = $this->jsonNewsRepository();

            if (!$this->config->isSupabaseConfigured()) {
                return $localRepository;
            }

            return new SynchronizedNewsRepository(
                $this->supabaseNewsRepository(),
                $localRepository,
                $this->updateLogger(),
            );
        });
    }

    public function updateLogger(): FileUpdateLogger
    {
        return $this->singleton(
            'update_logger',
            fn (): FileUpdateLogger => new FileUpdateLogger($this->config)
        );
    }

    public function articlePageCache(): ArticlePageCache
    {
        return $this->singleton(
            'article_page_cache',
            fn (): ArticlePageCache => new ArticlePageCache($this->config, $this->httpClient())
        );
    }

    public function articleContentExtractor(): ArticleContentExtractor
    {
        return $this->singleton(
            'article_content_extractor',
            fn (): ArticleContentExtractor => new ArticleContentExtractor($this->articlePageCache())
        );
    }

    public function abiFeedClient(): AbiFeedClient
    {
        return $this->singleton(
            'abi_feed_client',
            fn (): AbiFeedClient => new AbiFeedClient(
                $this->config,
                $this->httpClient(),
                $this->articleContentExtractor(),
            )
        );
    }

    public function listNewsUseCase(): ListNewsUseCase
    {
        return $this->singleton(
            'list_news_use_case',
            fn (): ListNewsUseCase => new ListNewsUseCase($this->newsRepository())
        );
    }

    public function updateNewsUseCase(): UpdateNewsUseCase
    {
        return $this->singleton(
            'update_news_use_case',
            fn (): UpdateNewsUseCase => new UpdateNewsUseCase(
                $this->abiFeedClient(),
                $this->updateNewsRepository(),
                $this->newsNormalizer(),
                $this->updateLogger(),
                $this->config,
            )
        );
    }

    public function migrateNewsToSupabaseUseCase(): MigrateNewsToSupabaseUseCase
    {
        return $this->singleton(
            'migrate_news_use_case',
            fn (): MigrateNewsToSupabaseUseCase => new MigrateNewsToSupabaseUseCase(
                $this->jsonNewsRepository(),
                $this->supabaseNewsRepository(),
                $this->newsNormalizer(),
                $this->config,
            )
        );
    }

    /**
     * @template T
     *
     * @param callable():T $factory
     *
     * @return T
     */
    private function singleton(string $key, callable $factory): mixed
    {
        if (!array_key_exists($key, $this->instances)) {
            $this->instances[$key] = $factory();
        }

        return $this->instances[$key];
    }
}
