<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Abi;

use PortalNoticias\Shared\Config\AppConfig;
use PortalNoticias\Shared\Http\HttpClientInterface;

final class ArticlePageCache
{
    /**
     * @var array<string, string>
     */
    private array $memoryCache = [];

    public function __construct(
        private readonly AppConfig $config,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getHtml(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (isset($this->memoryCache[$url])) {
            return $this->memoryCache[$url];
        }

        $cacheDirectory = $this->config->cachePath();

        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0775, true);
        }

        $cacheFile = $cacheDirectory . DIRECTORY_SEPARATOR . 'page_' . sha1($url) . '.html';

        if (is_file($cacheFile) && is_readable($cacheFile) && filemtime($cacheFile) !== false) {
            $age = time() - filemtime($cacheFile);

            if ($age < 21600) {
                $cachedHtml = (string) file_get_contents($cacheFile);
                $this->memoryCache[$url] = $cachedHtml;

                return $cachedHtml;
            }
        }

        $response = $this->httpClient->request(
            'GET',
            $url,
            ['User-Agent: ' . $this->config->appName() . ' RSS Reader/1.0'],
            null,
            true,
        );

        if (!$response->isSuccessful()) {
            $this->memoryCache[$url] = '';

            return '';
        }

        $html = (string) $response->body();
        $this->memoryCache[$url] = $html;
        @file_put_contents($cacheFile, $html, LOCK_EX);

        return $html;
    }
}
