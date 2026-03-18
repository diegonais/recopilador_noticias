<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Http;

interface HttpClientInterface
{
    /**
     * @param array<int, string> $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        bool $followLocation = true,
        int $connectTimeout = 10,
        int $timeout = 20,
    ): HttpResponse;
}
