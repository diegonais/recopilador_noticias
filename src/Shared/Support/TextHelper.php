<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Support;

final class TextHelper
{
    public static function cleanHtml(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::normalizeWhitespace(strip_tags($decoded));
    }

    public static function normalizeWhitespace(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);

        return trim((string) $normalized);
    }

    public static function fallback(string $text, string $default = 'Sin resumen disponible.'): string
    {
        $normalized = self::normalizeWhitespace($text);

        return $normalized !== '' ? $normalized : $default;
    }

    public static function removeFeedFooter(string $text): string
    {
        $text = self::normalizeWhitespace($text);

        if ($text === '') {
            return '';
        }

        $patterns = [
            '/\s*La entrada .*? se public(?:A3|o|O) primero en ABI\.?$/u',
            '/\s*La entrada .*? se publico primero en ABI\.?$/u',
            '/\s*Navegaci(?:A3|o|O)n de entradas.*$/u',
            '/\s+(?:(?:\/\/?[A-Z]{2,6}\/\/?)|(?:[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*))?\s*Navegaci\S*\s+de\s+entradas[\s\S]*$/iu',
            '/\s*\.?\s*(?=[\/A-Za-z]*\/)(?:\/{0,3}[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*\/{0,3})\s*$/u',
        ];

        $cleaned = preg_replace($patterns, '', $text);
        $cleaned = self::normalizeWhitespace((string) $cleaned);
        $cleaned = preg_replace('/\s*(?:\/\/\/|\/\/)[A-Za-z]{2,5}(?:\/\/\/|\/\/)\s*$/u', '', $cleaned);
        $cleaned = preg_replace('/\s*[A-Za-z]{2,5}(?:\/[A-Za-z]{2,5})+\s*$/u', '', $cleaned);
        $cleaned = preg_replace('/([.!?\"])\s+[A-Z]{2,5}\s*$/u', '$1', $cleaned);

        return trim((string) $cleaned);
    }
}
