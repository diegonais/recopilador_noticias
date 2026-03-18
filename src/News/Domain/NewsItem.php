<?php

declare(strict_types=1);

namespace PortalNoticias\News\Domain;

use JsonSerializable;
use PortalNoticias\Shared\Support\DateHelper;
use PortalNoticias\Shared\Support\TextHelper;

final class NewsItem implements JsonSerializable
{
    public function __construct(
        private readonly string $title,
        private readonly string $summary,
        private readonly string $link,
        private readonly string $source,
        private readonly string $publishedAt,
        private readonly string $image,
        private readonly string $guid,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload, string $timezone): ?self
    {
        $title = TextHelper::fallback(TextHelper::cleanHtml((string) ($payload['title'] ?? '')), '');
        $link = trim((string) ($payload['link'] ?? ''));
        $publishedAt = DateHelper::toStorageFormat((string) ($payload['published_at'] ?? ''), $timezone);

        if ($title === '' || $link === '' || $publishedAt === '') {
            return null;
        }

        $summary = TextHelper::fallback(
            TextHelper::removeFeedFooter(
                TextHelper::cleanHtml((string) ($payload['summary'] ?? ''))
            ),
            'Sin resumen disponible.',
        );

        $guid = trim((string) ($payload['guid'] ?? ''));

        if ($guid === '') {
            $guid = sha1($link . '|' . $publishedAt);
        }

        return new self(
            $title,
            $summary,
            $link,
            TextHelper::fallback((string) ($payload['source'] ?? ''), 'ABI'),
            $publishedAt,
            trim((string) ($payload['image'] ?? '')),
            $guid,
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function link(): string
    {
        return $this->link;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function publishedAt(): string
    {
        return $this->publishedAt;
    }

    public function image(): string
    {
        return $this->image;
    }

    public function guid(): string
    {
        return $this->guid;
    }

    public function uniqueKey(): string
    {
        if ($this->guid !== '') {
            return 'guid:' . $this->guid;
        }

        return 'link:' . $this->link;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'link' => $this->link,
            'source' => $this->source,
            'published_at' => $this->publishedAt,
            'image' => $this->image,
            'guid' => $this->guid,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
