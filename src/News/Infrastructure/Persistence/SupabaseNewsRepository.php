<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Persistence;

use PortalNoticias\News\Domain\NewsItem;
use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\Shared\Config\AppConfig;
use PortalNoticias\Shared\Http\HttpClientInterface;
use RuntimeException;

final class SupabaseNewsRepository implements NewsRepositoryInterface
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function findLatest(int $limit): array
    {
        $this->guardConfiguration();

        $queryParams = [
            'select' => 'guid,title,summary,link,source,published_at,image,created_at,updated_at',
            'order' => 'published_at.desc',
        ];

        if ($limit > 0 && $limit !== PHP_INT_MAX) {
            $queryParams['limit'] = $limit;
        }

        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request(
            'GET',
            $this->buildTableUrl() . '?' . $query,
            $this->defaultHeaders(),
            null,
            false,
        );

        if (!$response->isSuccessful()) {
            throw new RuntimeException('No se pudo leer noticias desde Supabase.');
        }

        $decoded = $this->decodeJson($response->body());

        if (!is_array($decoded)) {
            throw new RuntimeException('La respuesta de Supabase no tiene el formato esperado.');
        }

        $items = [];

        foreach ($decoded as $record) {
            if (!is_array($record)) {
                continue;
            }

            $item = NewsItem::fromArray($record, $this->config->timezone());

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function saveAll(array $items): void
    {
        $this->guardConfiguration();

        if (count($items) === 0) {
            return;
        }

        $payload = array_map(static function (NewsItem $item): array {
            return [
                'guid' => $item->guid(),
                'title' => $item->title(),
                'summary' => $item->summary(),
                'link' => $item->link(),
                'source' => $item->source(),
                'published_at' => $item->publishedAt(),
                'image' => $item->image() !== '' ? $item->image() : null,
            ];
        }, $items);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($body)) {
            throw new RuntimeException('No se pudo preparar la carga para Supabase.');
        }

        $query = http_build_query([
            'on_conflict' => 'guid',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request(
            'POST',
            $this->buildTableUrl() . '?' . $query,
            array_merge(
                $this->defaultHeaders(),
                ['Content-Type: application/json', 'Prefer: resolution=merge-duplicates,return=minimal']
            ),
            $body,
            false,
        );

        if ($response->statusCode() < 200 || $response->statusCode() >= 300) {
            throw new RuntimeException('No se pudo guardar noticias en Supabase.');
        }
    }

    public function latestUpdatedAt(): ?string
    {
        $this->guardConfiguration();

        $query = http_build_query([
            'select' => 'updated_at',
            'order' => 'updated_at.desc',
            'limit' => 1,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request(
            'GET',
            $this->buildTableUrl() . '?' . $query,
            $this->defaultHeaders(),
            null,
            false,
        );

        if (!$response->isSuccessful()) {
            throw new RuntimeException('No se pudo leer la fecha de actualizacion desde Supabase.');
        }

        $decoded = $this->decodeJson($response->body());

        if (!is_array($decoded) || !isset($decoded[0]['updated_at'])) {
            return null;
        }

        $updatedAt = trim((string) $decoded[0]['updated_at']);

        return $updatedAt !== '' ? $updatedAt : null;
    }

    private function guardConfiguration(): void
    {
        if (!$this->config->isSupabaseConfigured()) {
            throw new RuntimeException('Supabase no esta configurado.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function defaultHeaders(): array
    {
        return [
            'apikey: ' . $this->config->supabaseServiceRoleKey(),
            'Authorization: Bearer ' . $this->config->supabaseServiceRoleKey(),
            'Accept: application/json',
        ];
    }

    private function buildTableUrl(): string
    {
        return $this->config->supabaseUrl() . '/rest/v1/' . rawurlencode($this->config->supabaseTable());
    }

    /**
     * @return mixed
     */
    private function decodeJson(?string $payload): mixed
    {
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}

