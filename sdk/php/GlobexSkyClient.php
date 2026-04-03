<?php
/**
 * GlobexSky PHP SDK
 *
 * Usage:
 *   $client = new GlobexSkyClient('gsk_live_xxxxxx');
 *   $products = $client->products()->list(['category' => 'electronics']);
 *   $order = $client->orders()->create(['items' => [...]]);
 *
 * Methods mirror API endpoints.
 * Handles auth, pagination, error handling.
 */

class GlobexSkyClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    /**
     * @param string $apiKey   Your GlobexSky API key (gsk_live_xxx or gsk_test_xxx)
     * @param string $baseUrl  API base URL (default: https://globexsky.com/api/v1/gateway.php)
     * @param int    $timeout  Request timeout in seconds
     */
    public function __construct(string $apiKey, string $baseUrl = 'https://globexsky.com/api/v1/gateway.php', int $timeout = 30)
    {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * Products resource
     */
    public function products(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'products');
    }

    /**
     * Orders resource
     */
    public function orders(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'orders');
    }

    /**
     * Cart resource
     */
    public function cart(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'cart');
    }

    /**
     * Users resource
     */
    public function users(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'users');
    }

    /**
     * Reviews resource
     */
    public function reviews(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'reviews');
    }

    /**
     * Shipping resource
     */
    public function shipping(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'shipping');
    }

    /**
     * Dropship resource
     */
    public function dropship(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'dropship');
    }

    /**
     * Webhooks resource
     */
    public function webhooks(): GlobexSkyResource
    {
        return new GlobexSkyResource($this, 'webhooks');
    }

    /**
     * Make an API request.
     *
     * @param string $method    HTTP method
     * @param string $resource  Resource name
     * @param string $action    Action name
     * @param array  $params    Query params (GET) or body (POST/PUT)
     * @return array            Decoded JSON response
     * @throws GlobexSkyException
     */
    public function request(string $method, string $resource, string $action, array $params = []): array
    {
        $url = $this->baseUrl . '?resource=' . urlencode($resource) . '&action=' . urlencode($action);

        $ch = curl_init();
        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if (in_array(strtoupper($method), ['GET', 'DELETE'])) {
            if ($params) {
                $url .= '&' . http_build_query($params);
            }
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new GlobexSkyException("cURL error: $curlError");
        }

        $decoded = json_decode($responseBody, true);
        if ($decoded === null) {
            throw new GlobexSkyException("Invalid JSON response: $responseBody");
        }

        if ($httpCode >= 400) {
            $errorMsg = $decoded['errors'][0]['message'] ?? "HTTP $httpCode error";
            throw new GlobexSkyException($errorMsg, $httpCode);
        }

        return $decoded;
    }
}

/**
 * API Resource accessor (products, orders, etc.)
 */
class GlobexSkyResource
{
    private GlobexSkyClient $client;
    private string $resource;

    public function __construct(GlobexSkyClient $client, string $resource)
    {
        $this->client   = $client;
        $this->resource = $resource;
    }

    public function list(array $params = []): array
    {
        return $this->client->request('GET', $this->resource, 'list', $params);
    }

    public function detail(int $id): array
    {
        return $this->client->request('GET', $this->resource, 'detail', ['id' => $id]);
    }

    public function create(array $data): array
    {
        return $this->client->request('POST', $this->resource, 'create', $data);
    }

    public function update(int $id, array $data): array
    {
        return $this->client->request('PUT', $this->resource, 'update', array_merge($data, ['id' => $id]));
    }

    public function delete(int $id): array
    {
        return $this->client->request('DELETE', $this->resource, 'delete', ['id' => $id]);
    }

    /**
     * Generic action call
     */
    public function action(string $action, array $params = [], string $method = 'GET'): array
    {
        return $this->client->request($method, $this->resource, $action, $params);
    }
}

/**
 * GlobexSky API Exception
 */
class GlobexSkyException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
