<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Abi;

use PortalNoticias\News\Domain\NewsSourceInterface;
use PortalNoticias\Shared\Config\AppConfig;
use PortalNoticias\Shared\Http\HttpClientInterface;
use PortalNoticias\Shared\Support\DateHelper;
use PortalNoticias\Shared\Support\TextHelper;
use RuntimeException;
use SimpleXMLElement;

final class AbiFeedClient implements NewsSourceInterface
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly HttpClientInterface $httpClient,
        private readonly ArticleContentExtractor $contentExtractor,
    ) {
    }

    public function fetchLatest(): array
    {
        $response = $this->httpClient->request(
            'GET',
            $this->config->abiRssUrl(),
            ['User-Agent: ' . $this->config->appName() . ' RSS Reader/1.0'],
            null,
            true,
        );

        if (!$response->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'No se pudo leer el feed RSS de ABI. status=%d error=%s',
                $response->statusCode(),
                $response->error() ?? 'sin_detalle',
            ));
        }

        $xml = $this->parseXml((string) $response->body());

        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('No se pudo interpretar el XML del feed RSS de ABI.');
        }

        $items = [];

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = $this->mapItem($item);
            }
        }

        return $items;
    }

    private function parseXml(string $content): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            libxml_clear_errors();

            return null;
        }

        libxml_clear_errors();

        return $xml;
    }

    /**
     * @return array<string, string>
     */
    private function mapItem(SimpleXMLElement $item): array
    {
        $title = TextHelper::fallback(TextHelper::cleanHtml((string) ($item->title ?? '')), 'Sin titulo');
        $description = isset($item->description) ? (string) $item->description : '';
        $contentEncoded = $this->getContentEncoded($item);
        $link = trim((string) ($item->link ?? ''));
        $guid = trim((string) ($item->guid ?? ''));
        $publishedRaw = $this->resolvePublishedDate($item);
        $publishedAt = DateHelper::toStorageFormat($publishedRaw, $this->config->timezone());

        if ($guid === '') {
            $guid = sha1($link . '|' . $title . '|' . $publishedAt);
        }

        return [
            'title' => $title,
            'summary' => $this->contentExtractor->extractSummary($description, $contentEncoded, $link),
            'link' => $link,
            'source' => 'ABI',
            'published_at' => $publishedAt,
            'image' => $this->extractImage($item, $description, $contentEncoded, $link),
            'guid' => $guid,
        ];
    }

    private function resolvePublishedDate(SimpleXMLElement $item): string
    {
        if (isset($item->pubDate)) {
            return (string) $item->pubDate;
        }

        if (isset($item->published)) {
            return (string) $item->published;
        }

        if (isset($item->updated)) {
            return (string) $item->updated;
        }

        return '';
    }

    private function getContentEncoded(SimpleXMLElement $item): string
    {
        $namespaces = $item->getNamespaces(true);

        if (!isset($namespaces['content'])) {
            return '';
        }

        $content = $item->children($namespaces['content']);

        return isset($content->encoded) ? (string) $content->encoded : '';
    }

    private function extractImage(SimpleXMLElement $item, string $description, string $contentEncoded, string $link): string
    {
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);

            foreach (['content', 'thumbnail'] as $nodeName) {
                if (!isset($media->{$nodeName})) {
                    continue;
                }

                foreach ($media->{$nodeName} as $node) {
                    $attributes = $node->attributes();
                    $url = trim((string) ($attributes['url'] ?? ''));

                    if ($url !== '') {
                        return $url;
                    }
                }
            }
        }

        if (isset($item->enclosure)) {
            foreach ($item->enclosure as $enclosure) {
                $attributes = $enclosure->attributes();
                $url = trim((string) ($attributes['url'] ?? ''));

                if ($url !== '') {
                    return $url;
                }
            }
        }

        return $this->contentExtractor->extractImageFromMarkupOrPage($description, $contentEncoded, $link);
    }
}
