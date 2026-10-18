<?php

namespace WHMCS\Module\Server\RealPanel;

class RealPanelAPI
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct(string $baseUrl, string $apiKey, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * Build from WHMCS server params.
     */
    public static function fromParams(array $params): self
    {
        $protocol = !empty($params['serversecure']) ? 'https' : 'http';
        $host = $params['serverhostname'] ?: $params['serverip'];
        $port = $params['serverport'] ?: ($protocol === 'https' ? '443' : '80');
        $portSuffix = ($port === '80' && $protocol === 'http') || ($port === '443' && $protocol === 'https') ? '' : ":{$port}";
        
        $baseUrl = "{$protocol}://{$host}{$portSuffix}/api/v1";

        return new self($baseUrl, $params['serveraccesshash']);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['result' => 'error', 'message' => 'Connection failed: ' . $error];
        }

        $decoded = json_decode($response, true);

        if (!$decoded) {
            return ['result' => 'error', 'message' => "Invalid response from server (HTTP {$httpCode})"];
        }

        return $decoded;
    }
}
