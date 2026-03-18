<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Abi;

use PortalNoticias\Shared\Support\TextHelper;

final class ArticleContentExtractor
{
    public function __construct(private readonly ArticlePageCache $pageCache)
    {
    }

    public function extractSummary(string $description, string $contentEncoded, string $link): string
    {
        $articleText = $this->extractArticleTextFromPage($link);

        if ($articleText !== '') {
            return $articleText;
        }

        $summarySource = $description !== '' ? $description : $contentEncoded;

        return TextHelper::fallback(
            TextHelper::removeFeedFooter(
                TextHelper::cleanHtml($summarySource)
            )
        );
    }

    public function extractImageFromMarkupOrPage(string $description, string $contentEncoded, string $link): string
    {
        foreach ([$description, $contentEncoded] as $html) {
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches) === 1) {
                return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        return $this->extractImageFromPage($link);
    }

    private function extractImageFromPage(string $link): string
    {
        $html = $this->pageCache->getHtml($link);

        if ($html === '') {
            return '';
        }

        $patterns = [
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
            '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/i',
            '/<img[^>]+class=["\'][^"\']*wp-post-image[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i',
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*wp-post-image[^"\']*["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                $image = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($image !== '') {
                    return $image;
                }
            }
        }

        return '';
    }

    private function extractArticleTextFromPage(string $link): string
    {
        $html = $this->pageCache->getHtml($link);

        if ($html === '') {
            return '';
        }

        if (preg_match('/<article[^>]+class=["\'][^"\']*page-content-single[^"\']*["\'][^>]*>(.*?)<\/article>/is', $html, $matches) !== 1) {
            return '';
        }

        $articleHtml = $matches[1];
        $articleHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $articleHtml);
        $articleHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $articleHtml);
        $articleHtml = preg_replace('/<div[^>]+class=["\'][^"\']*post-share[^"\']*["\'][^>]*>.*?<\/div>/is', ' ', $articleHtml);

        $text = TextHelper::cleanHtml((string) $articleHtml);
        $text = TextHelper::removeFeedFooter($text);
        $text = preg_replace('/\s+[A-Z][A-Za-z]{1,5}(?:\/[A-Z][A-Za-z]{1,5})+$/u', '', $text);

        return trim((string) $text);
    }
}
