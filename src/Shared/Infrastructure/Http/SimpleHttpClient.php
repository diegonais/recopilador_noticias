<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Infrastructure\Http;

use PortalNoticias\Shared\Http\HttpClientInterface;
use PortalNoticias\Shared\Http\HttpResponse;

final class SimpleHttpClient implements HttpClientInterface
{
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        bool $followLocation = true,
        int $connectTimeout = 10,
        int $timeout = 20,
    ): HttpResponse {
        if (function_exists('curl_init')) {
            return $this->requestWithCurl($method, $url, $headers, $body, $followLocation, $connectTimeout, $timeout);
        }

        return $this->requestWithStreams($method, $url, $headers, $body, $timeout);
    }

    /**
     * @param array<int, string> $headers
     */
    private function requestWithCurl(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        bool $followLocation,
        int $connectTimeout,
        int $timeout,
    ): HttpResponse {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        curl_close($handle);

        return new HttpResponse(
            $statusCode,
            is_string($rawBody) ? $rawBody : null,
            $error !== '' ? $error : null,
        );
    }

    /**
     * @param array<int, string> $headers
     */
    private function requestWithStreams(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
    ): HttpResponse {
        $options = [
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        $context = stream_context_create($options);
        $rawBody = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];

        return new HttpResponse(
            $this->extractStatusCode($responseHeaders),
            is_string($rawBody) ? $rawBody : null,
            $rawBody === false ? 'stream_request_failed' : null,
        );
    }

    /**
     * @param array<int, string> $headers
     */
    private function extractStatusCode(array $headers): int
    {
        if (!isset($headers[0])) {
            return 0;
        }

        if (preg_match('/\s(\d{3})\s/', $headers[0], $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
