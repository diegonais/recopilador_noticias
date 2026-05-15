<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Support;

final class TextHelper
{
    public static function cleanHtml(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $decoded);
        $decoded = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', (string) $decoded);
        $decoded = preg_replace('/<\s*br\s*\/?>/i', "\n", (string) $decoded);
        $decoded = preg_replace('/<\s*\/\s*(?:p|div|section|article|header|footer|h[1-6]|blockquote|li|ul|ol|table|tr)\s*>/i', "\n\n", (string) $decoded);
        $decoded = preg_replace('/<\s*(?:p|div|section|article|header|footer|h[1-6]|blockquote|li|ul|ol|table|tr)\b[^>]*>/i', "\n\n", (string) $decoded);

        return self::normalizeReadableWhitespace(strip_tags((string) $decoded));
    }

    public static function normalizeWhitespace(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);

        return trim((string) $normalized);
    }

    public static function normalizeReadableWhitespace(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = str_replace("\t", ' ', $normalized);
        $normalized = preg_replace('/[^\S\n]+/u', ' ', $normalized);
        $normalized = preg_replace('/ *\n */u', "\n", (string) $normalized);
        $normalized = preg_replace('/\n{3,}/u', "\n\n", (string) $normalized);
        $lines = array_map('trim', explode("\n", (string) $normalized));

        return trim(implode("\n", $lines));
    }

    public static function fallback(string $text, string $default = 'Sin resumen disponible.'): string
    {
        $normalized = self::normalizeReadableWhitespace($text);

        return $normalized !== '' ? $normalized : $default;
    }

    public static function removeFeedFooter(string $text): string
    {
        $text = self::normalizeReadableWhitespace($text);

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
        $cleaned = self::normalizeReadableWhitespace((string) $cleaned);
        $cleaned = preg_replace('/\s*(?:\/\/\/|\/\/)[A-Za-z]{2,5}(?:\/\/\/|\/\/)\s*$/u', '', $cleaned);
        $cleaned = preg_replace('/\s*[A-Za-z]{2,5}(?:\/[A-Za-z]{2,5})+\s*$/u', '', $cleaned);
        $cleaned = preg_replace('/([.!?\"])\s+[A-Z]{2,5}\s*$/u', '$1', $cleaned);

        return trim((string) $cleaned);
    }
}
