<?php

namespace ChannelEngineCore\Infrastructure\HTTP;

use Exception;

class HttpClient
{
    private array $defaultHeaders = [];
    private int $timeout = 30;

    /**
     * Set default headers for all requests
     *
     * @param array $headers
     *
     * @return self
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;

        return $this;
    }

    /**
     * Set timeout for requests
     *
     * @param int $seconds
     *
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Make GET request
     *
     * @param string $url
     * @param array $headers
     *
     * @return array
     *
     * @throws Exception
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * Make POST request
     *
     * @param string $url
     * @param mixed $data
     * @param array $headers
     *
     * @return array
     *
     * @throws Exception
     */
    public function post(string $url, mixed $data = null, array $headers = []): array
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * Make PUT request
     *
     * @param string $url
     * @param mixed $data
     * @param array $headers
     *
     * @return array
     *
     * @throws Exception
     */
    public function put(string $url, mixed $data = null, array $headers = []): array
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    /**
     * Make DELETE request
     *
     * @param string $url
     * @param array $headers
     *
     * @return array
     *
     * @throws Exception
     */
    public function delete(string $url, array $headers = []): array
    {
        return $this->request('DELETE', $url, null, $headers);
    }

    /**
     * Make HTTP request
     *
     * @param string $method
     * @param string $url
     * @param mixed $data
     * @param array $headers
     *
     * @return array
     *
     * @throws Exception
     */
    private function request(string $method, string $url, mixed $data = null, array $headers = []): array
    {
        $headers = array_merge($this->defaultHeaders, $headers);

        $options = [
            'http' => [
                'method' => $method,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'header' => $this->buildHeaders($headers)
            ]
        ];

        if ($data !== null && in_array($method, ['POST', 'PUT'])) {
            if (is_array($data) || is_object($data)) {
                $options['http']['content'] = json_encode($data);
                if (!isset($headers['Content-Type'])) {
                    $options['http']['header'] .= "Content-Type: application/json\r\n";
                }
            } else {
                $options['http']['content'] = $data;
            }
        }

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new Exception('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
        }

        $responseHeaders = $http_response_header ?? [];
        $statusCode = $this->parseStatusCode($responseHeaders);

        $responseData = $this->parseResponse($response);

        return [
            'status_code' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $responseData,
            'raw_body' => $response
        ];
    }

    /**
     * Build headers string
     *
     * @param array $headers
     *
     * @return string
     */
    private function buildHeaders(array $headers): string
    {
        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = $key . ': ' . $value;
        }

        return implode("\r\n", $headerLines) . "\r\n";
    }

    /**
     * Parse status code from response headers
     *
     * @param array $headers
     *
     * @return int
     */
    private function parseStatusCode(array $headers): int
    {
        if (!empty($headers) && preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Parse response body
     *
     * @param string $response
     *
     * @return mixed
     */
    private function parseResponse(string $response): mixed
    {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $response;
    }
}