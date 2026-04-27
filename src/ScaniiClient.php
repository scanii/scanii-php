<?php

declare(strict_types=1);

namespace Scanii;

use CURLFile;
use InvalidArgumentException;
use Scanii\Models\ScaniiAccountInfo;
use Scanii\Models\ScaniiAccountInfoApiKey;
use Scanii\Models\ScaniiAccountInfoUser;
use Scanii\Models\ScaniiAuthToken;
use Scanii\Models\ScaniiPendingResult;
use Scanii\Models\ScaniiProcessingResult;

/**
 * Thread-friendly client for the Scanii content processing API.
 *
 * The client is a thin wrapper around the REST API: per SDK Principle 3, it
 * does no retrying, batching, or concurrency management. Callers are
 * expected to handle those concerns at the layer that fits their workload.
 *
 * @see https://scanii.github.io/openapi/v22/
 */
final class ScaniiClient
{
    public const string VERSION = '6.0.0';

    private const string API_VERSION_PATH = '/v2.2';
    private const string DEFAULT_USER_AGENT_PREFIX = 'scanii-php/v';

    private readonly string $baseUrl;
    private readonly string $userPwd;
    private readonly string $userAgent;

    /**
     * @param string $key      API key (or auth token id)
     * @param string $secret   API secret — pass an empty string when $key is a token
     * @param string $target   one of the ScaniiTarget constants, or any base URL
     *                         (used by scanii-cli — e.g. http://localhost:4000)
     * @param string $userAgent optional user-agent to prepend to the SDK's default
     */
    private function __construct(
        string $key,
        string $secret,
        string $target,
        string $userAgent,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('key must not be empty');
        }
        if (str_contains($key, ':')) {
            throw new InvalidArgumentException('key must not contain a colon');
        }

        $this->baseUrl = rtrim($target, '/') . self::API_VERSION_PATH;
        $this->userPwd = $key . ':' . $secret;

        $defaultUa = self::DEFAULT_USER_AGENT_PREFIX . self::VERSION;
        $this->userAgent = $userAgent === '' ? $defaultUa : $userAgent . ' ' . $defaultUa;
    }

    /**
     * Build a client with API key + secret credentials.
     */
    public static function create(
        string $key,
        string $secret,
        string $target = ScaniiTarget::AUTO,
        string $userAgent = '',
    ): self {
        if ($secret === '') {
            throw new InvalidArgumentException('secret must not be empty; use createFromToken() for token-based auth');
        }
        return new self($key, $secret, $target, $userAgent);
    }

    /**
     * Build a client that authenticates with a previously minted auth token.
     */
    public static function createFromToken(
        ScaniiAuthToken $token,
        string $target = ScaniiTarget::AUTO,
        string $userAgent = '',
    ): self {
        return new self($token->resourceId, '', $target, $userAgent);
    }

    /**
     * Submit a file for synchronous scanning.
     *
     * @param array<string, string> $metadata
     *
     * @see https://scanii.github.io/openapi/v22/ — POST /files
     */
    public function process(
        string $path,
        array $metadata = [],
        ?string $callback = null,
    ): ScaniiProcessingResult {
        $this->assertReadable($path);

        $fields = $this->buildMultipart($path, $metadata, $callback);
        [$status, $body, $headers] = $this->request('POST', '/files', body: $fields);

        if ($status !== 201) {
            $this->throwForStatus($status, $body, $headers);
        }

        return $this->buildProcessingResult($status, $body, $headers);
    }

    /**
     * Submit a file for asynchronous scanning.
     *
     * @param array<string, string> $metadata
     *
     * @see https://scanii.github.io/openapi/v22/ — POST /files/async
     */
    public function processAsync(
        string $path,
        array $metadata = [],
        ?string $callback = null,
    ): ScaniiPendingResult {
        $this->assertReadable($path);

        $fields = $this->buildMultipart($path, $metadata, $callback);
        [$status, $body, $headers] = $this->request('POST', '/files/async', body: $fields);

        if ($status !== 202) {
            $this->throwForStatus($status, $body, $headers);
        }

        return $this->buildPendingResult($status, $body, $headers);
    }

    /**
     * Instruct Scanii to download a remote URL and scan it asynchronously.
     *
     * @param array<string, string> $metadata
     *
     * @see https://scanii.github.io/openapi/v22/ — POST /files/fetch
     */
    public function fetch(
        string $location,
        array $metadata = [],
        ?string $callback = null,
    ): ScaniiPendingResult {
        $form = ['location' => $location];
        if ($callback !== null && $callback !== '') {
            $form['callback'] = $callback;
        }
        foreach ($metadata as $k => $v) {
            $form["metadata[$k]"] = $v;
        }

        [$status, $body, $headers] = $this->request(
            'POST',
            '/files/fetch',
            body: http_build_query($form, '', '&', PHP_QUERY_RFC3986),
            contentType: 'application/x-www-form-urlencoded',
        );

        if ($status !== 202) {
            $this->throwForStatus($status, $body, $headers);
        }

        return $this->buildPendingResult($status, $body, $headers);
    }

    /**
     * Fetch the result of a previously submitted scan by id.
     *
     * @see https://scanii.github.io/openapi/v22/ — GET /files/{id}
     */
    public function retrieve(string $id): ScaniiProcessingResult
    {
        if ($id === '') {
            throw new InvalidArgumentException('id must not be empty');
        }

        [$status, $body, $headers] = $this->request('GET', '/files/' . rawurlencode($id));

        if ($status !== 200) {
            $this->throwForStatus($status, $body, $headers);
        }

        return $this->buildProcessingResult($status, $body, $headers);
    }

    /**
     * Verify that the configured credentials reach the API.
     *
     * @see https://scanii.github.io/openapi/v22/ — GET /ping
     */
    public function ping(): bool
    {
        [$status, $body, $headers] = $this->request('GET', '/ping');

        if ($status === 200) {
            return true;
        }
        $this->throwForStatus($status, $body, $headers);
    }

    /**
     * Mint a short-lived auth token.
     *
     * @param int $timeoutSeconds how long the token should remain valid (>0)
     *
     * @see https://scanii.github.io/openapi/v22/ — POST /auth/tokens
     */
    public function createAuthToken(int $timeoutSeconds = 300): ScaniiAuthToken
    {
        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('timeoutSeconds must be positive');
        }

        [$status, $body, $headers] = $this->request(
            'POST',
            '/auth/tokens',
            body: http_build_query(['timeout' => $timeoutSeconds], '', '&', PHP_QUERY_RFC3986),
            contentType: 'application/x-www-form-urlencoded',
        );

        if ($status !== 201 && $status !== 200) {
            $this->throwForStatus($status, $body, $headers);
        }

        return $this->buildAuthToken($status, $body, $headers);
    }

    /**
     * Inspect a previously created auth token.
     *
     * @see https://scanii.github.io/openapi/v22/ — GET /auth/tokens/{id}
     */
    public function retrieveAuthToken(string $id): ScaniiAuthToken
    {
        if ($id === '') {
            throw new InvalidArgumentException('id must not be empty');
        }

        [$status, $body, $headers] = $this->request('GET', '/auth/tokens/' . rawurlencode($id));

        if ($status !== 200) {
            $this->throwForStatus($status, $body, $headers);
        }

        return $this->buildAuthToken($status, $body, $headers);
    }

    /**
     * Revoke an auth token.
     *
     * @see https://scanii.github.io/openapi/v22/ — DELETE /auth/tokens/{id}
     */
    public function deleteAuthToken(string $id): void
    {
        if ($id === '') {
            throw new InvalidArgumentException('id must not be empty');
        }

        [$status, $body, $headers] = $this->request('DELETE', '/auth/tokens/' . rawurlencode($id));

        if ($status !== 204) {
            $this->throwForStatus($status, $body, $headers);
        }
    }

    /**
     * Retrieve account metadata: balance, users, API keys, subscription.
     *
     * @see https://scanii.github.io/openapi/v22/ — GET /account.json
     */
    public function retrieveAccountInfo(): ScaniiAccountInfo
    {
        [$status, $body, $headers] = $this->request('GET', '/account.json');

        if ($status !== 200) {
            $this->throwForStatus($status, $body, $headers);
        }

        return $this->buildAccountInfo($status, $body, $headers);
    }

    // -- HTTP layer --------------------------------------------------------

    /**
     * Perform an HTTP request via ext-curl.
     *
     * @param string|array<string, mixed>|null $body
     *
     * @return array{0: int, 1: string, 2: array<string, list<string>>}
     */
    private function request(
        string $method,
        string $path,
        string|array|null $body = null,
        ?string $contentType = null,
    ): array {
        $ch = curl_init();
        if ($ch === false) {
            throw new ScaniiException('curl_init failed');
        }

        $headers = [];
        $headerCallback = static function ($_ch, string $headerLine) use (&$headers): int {
            $len = strlen($headerLine);
            $colon = strpos($headerLine, ':');
            if ($colon !== false) {
                $name = strtolower(trim(substr($headerLine, 0, $colon)));
                $value = trim(substr($headerLine, $colon + 1));
                $headers[$name][] = $value;
            }
            return $len;
        };

        $requestHeaders = [
            'User-Agent: ' . $this->userAgent,
            'Accept: application/json',
            'Expect:', // disable cURL's 100-continue behavior on uploads
        ];
        if ($contentType !== null) {
            $requestHeaders[] = 'Content-Type: ' . $contentType;
        }

        $opts = [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_USERPWD => $this->userPwd,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => $headerCallback,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);

        $rawBody = curl_exec($ch);
        if ($rawBody === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            throw new ScaniiException("cURL transport error ($errno): $err");
        }

        /** @var int $status */
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return [$status, (string) $rawBody, $headers];
    }

    /**
     * @param array<string, string> $metadata
     *
     * @return array<string, mixed>
     */
    private function buildMultipart(string $path, array $metadata, ?string $callback): array
    {
        $fields = [
            'file' => new CURLFile($path, 'application/octet-stream', basename($path)),
        ];
        if ($callback !== null && $callback !== '') {
            $fields['callback'] = $callback;
        }
        foreach ($metadata as $k => $v) {
            $fields["metadata[$k]"] = $v;
        }
        return $fields;
    }

    private function assertReadable(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException("file at $path is not readable");
        }
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function throwForStatus(int $status, string $body, array $headers): never
    {
        $requestId = $this->headerValue($headers, 'x-scanii-request-id');
        $hostId = $this->headerValue($headers, 'x-scanii-host-id');
        $message = $this->extractErrorMessage($body) ?? "HTTP $status";

        if ($status === 401 || $status === 403) {
            throw new ScaniiAuthException($message, $status, $requestId, $hostId, $body);
        }

        if ($status === 429) {
            $retryAfter = $this->headerValue($headers, 'retry-after');
            $retryAfterSeconds = $retryAfter !== null && ctype_digit($retryAfter) ? (int) $retryAfter : null;
            throw new ScaniiRateLimitException($message, $status, $requestId, $hostId, $body, $retryAfterSeconds);
        }

        throw new ScaniiException($message, $status, $requestId, $hostId, $body);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        $name = strtolower($name);
        return $headers[$name][0] ?? null;
    }

    private function extractErrorMessage(string $body): ?string
    {
        if ($body === '') {
            return null;
        }
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            return $decoded['error'];
        }
        return $body;
    }

    /**
     * @param array<string, list<string>> $headers
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function commonMetadata(array $headers): array
    {
        return [
            $this->headerValue($headers, 'x-scanii-request-id'),
            $this->headerValue($headers, 'x-scanii-host-id'),
            $this->headerValue($headers, 'location'),
        ];
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function buildProcessingResult(int $status, string $body, array $headers): ScaniiProcessingResult
    {
        $json = $this->decodeJson($body);
        [$requestId, $hostId, $location] = $this->commonMetadata($headers);

        return new ScaniiProcessingResult(
            statusCode: $status,
            rawResponse: $body,
            resourceId: (string) ($json['id'] ?? ''),
            contentType: isset($json['content_type']) ? (string) $json['content_type'] : null,
            contentLength: isset($json['content_length']) ? (int) $json['content_length'] : null,
            findings: array_values(array_map(strval(...), $json['findings'] ?? [])),
            checksum: isset($json['checksum']) ? (string) $json['checksum'] : null,
            creationDate: isset($json['creation_date']) ? (string) $json['creation_date'] : null,
            metadata: array_map(strval(...), $json['metadata'] ?? []),
            error: isset($json['error']) && $json['error'] !== null ? (string) $json['error'] : null,
            requestId: $requestId,
            hostId: $hostId,
            resourceLocation: $location,
        );
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function buildPendingResult(int $status, string $body, array $headers): ScaniiPendingResult
    {
        $json = $this->decodeJson($body);
        [$requestId, $hostId, $location] = $this->commonMetadata($headers);

        return new ScaniiPendingResult(
            statusCode: $status,
            rawResponse: $body,
            resourceId: (string) ($json['id'] ?? ''),
            requestId: $requestId,
            hostId: $hostId,
            resourceLocation: $location,
        );
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function buildAuthToken(int $status, string $body, array $headers): ScaniiAuthToken
    {
        $json = $this->decodeJson($body);
        [$requestId, $hostId, $location] = $this->commonMetadata($headers);

        return new ScaniiAuthToken(
            statusCode: $status,
            rawResponse: $body,
            resourceId: (string) ($json['id'] ?? ''),
            creationDate: isset($json['creation_date']) ? (string) $json['creation_date'] : null,
            expirationDate: isset($json['expiration_date']) ? (string) $json['expiration_date'] : null,
            requestId: $requestId,
            hostId: $hostId,
            resourceLocation: $location,
        );
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function buildAccountInfo(int $status, string $body, array $headers): ScaniiAccountInfo
    {
        $json = $this->decodeJson($body);
        [$requestId, $hostId, $location] = $this->commonMetadata($headers);

        $users = [];
        foreach (($json['users'] ?? []) as $k => $v) {
            $users[(string) $k] = new ScaniiAccountInfoUser(
                creationDate: isset($v['creation_date']) ? (string) $v['creation_date'] : null,
                lastLoginDate: isset($v['last_login_date']) ? (string) $v['last_login_date'] : null,
            );
        }

        $keys = [];
        foreach (($json['keys'] ?? []) as $k => $v) {
            $keys[(string) $k] = new ScaniiAccountInfoApiKey(
                active: (bool) ($v['active'] ?? false),
                creationDate: isset($v['creation_date']) ? (string) $v['creation_date'] : null,
                lastSeenDate: isset($v['last_seen_date']) ? (string) $v['last_seen_date'] : null,
                detectionCategoriesEnabled: array_values(array_map(strval(...), $v['detection_categories_enabled'] ?? [])),
                tags: array_values(array_map(strval(...), $v['tags'] ?? [])),
            );
        }

        return new ScaniiAccountInfo(
            statusCode: $status,
            rawResponse: $body,
            name: (string) ($json['name'] ?? ''),
            balance: (int) ($json['balance'] ?? 0),
            startingBalance: (int) ($json['starting_balance'] ?? 0),
            billingEmail: isset($json['billing_email']) ? (string) $json['billing_email'] : null,
            subscription: isset($json['subscription']) ? (string) $json['subscription'] : null,
            creationDate: isset($json['creation_date']) ? (string) $json['creation_date'] : null,
            modificationDate: isset($json['modification_date']) ? (string) $json['modification_date'] : null,
            users: $users,
            keys: $keys,
            requestId: $requestId,
            hostId: $hostId,
            resourceLocation: $location,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ScaniiException('expected JSON object response, got: ' . substr($body, 0, 200));
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
