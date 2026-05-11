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
        $response = $this->executeCurlRequest(
            $method,
            $url,
            $headers,
            $body,
            $followLocation,
            $connectTimeout,
            $timeout,
            true,
        );

        if ($this->shouldRetryAbiWithoutPeerVerification($url, $response)) {
            return $this->executeCurlRequest(
                $method,
                $url,
                $headers,
                $body,
                $followLocation,
                $connectTimeout,
                $timeout,
                false,
            );
        }

        return $response;
    }

    /**
     * @param array<int, string> $headers
     */
    private function executeCurlRequest(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        bool $followLocation,
        int $connectTimeout,
        int $timeout,
        bool $verifyPeer,
    ): HttpResponse {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
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
        $response = $this->executeStreamRequest($method, $url, $headers, $body, $timeout, true);

        if ($this->shouldRetryAbiWithoutPeerVerification($url, $response)) {
            return $this->executeStreamRequest($method, $url, $headers, $body, $timeout, false);
        }

        return $response;
    }

    /**
     * @param array<int, string> $headers
     */
    private function executeStreamRequest(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        bool $verifyPeer,
    ): HttpResponse {
        $options = [
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers),
            ],
            'ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
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
            $rawBody === false ? $this->lastPhpErrorMessage('stream_request_failed') : null,
        );
    }

    private function shouldRetryAbiWithoutPeerVerification(string $url, HttpResponse $response): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (!in_array($host, ['abi.bo', 'www.abi.bo'], true)) {
            return false;
        }

        if ($response->statusCode() !== 0) {
            return false;
        }

        $error = strtolower((string) $response->error());

        return str_contains($error, 'ssl certificate')
            || str_contains($error, 'local issuer certificate')
            || str_contains($error, 'operation failed')
            || str_contains($error, 'stream_request_failed');
    }

    private function lastPhpErrorMessage(string $fallback): string
    {
        $error = error_get_last();

        if (!is_array($error) || !isset($error['message'])) {
            return $fallback;
        }

        return (string) $error['message'];
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
