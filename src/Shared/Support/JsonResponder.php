<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Support;

final class JsonResponder
{
    /**
     * @param array<string, mixed> $data
     */
    public static function send(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
