<?php

declare(strict_types=1);

namespace Babod\NetManager\Zyxel;

final class HttpSession
{
    /** @var resource */
    private $handle;

    private string $cookieJar;

    public function __construct(
        private readonly string $baseUrl,
        private readonly bool $verifySsl = false,
    ) {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'zyxel_cookie_');
        $this->handle = curl_init();
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
        if (is_file($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        return $this->request('GET', $path, $query, null, $headers);
    }

    public function post(string $path, array $query = [], ?string $body = null, array $headers = []): array
    {
        return $this->request('POST', $path, $query, $body, $headers);
    }

    public function postForm(string $path, array $fields, array $query = []): array
    {
        return $this->post($path, $query, http_build_query($fields), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    public function postJson(string $path, array $payload, array $query = []): array
    {
        return $this->post($path, $query, json_encode($payload, JSON_THROW_ON_ERROR), [
            'Content-Type: application/json',
        ]);
    }

    private function request(string $method, string $path, array $query, ?string $body, array $headers): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $defaultHeaders = [
            'Connection: close',
            'Accept: */*',
        ];

        curl_setopt_array($this->handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_POSTFIELDS => $body,
        ]);

        $raw = curl_exec($this->handle);
        if ($raw === false) {
            throw new \RuntimeException('HTTP hiba: ' . curl_error($this->handle));
        }

        $status = (int) curl_getinfo($this->handle, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr((string) $raw, 0, $headerSize);
        $responseBody = substr((string) $raw, $headerSize);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $responseBody,
        ];
    }
}
