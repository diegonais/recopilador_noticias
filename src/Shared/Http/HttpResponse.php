<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Http;

final class HttpResponse
{
    public function __construct(
        private readonly int $statusCode,
        private readonly ?string $body,
        private readonly ?string $error = null,
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 400 && $this->body !== null && $this->body !== '';
    }
}
