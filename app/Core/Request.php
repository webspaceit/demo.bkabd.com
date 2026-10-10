<?php

namespace App\Core;

/**
 * HTTP request abstraction (JSON body, form body, uploads, headers).
 */
final class Request
{
    private static ?self $current = null;

    /** @var array<string,mixed> */
    public array $query;
    /** @var array<string,mixed> */
    public array $body;
    /** @var array<string,mixed> */
    public array $files;
    /** @var array<string,mixed> */
    public array $server;

    private string $method;
    private string $path;
    /** @var array<string,string> Route params ({id} => value) */
    public array $params = [];
    private ?array $jsonCache = null;
    private ?array $ipRate = null;

    public function __construct(?string $method = null, ?string $path = null, ?array $query = null, ?array $body = null, ?array $files = null, ?array $server = null)
    {
        $this->server = $server ?? $_SERVER;
        $this->query = $query ?? $_GET;
        $this->files = $files ?? $_FILES;

        $rawMethod = strtoupper($method ?? ($this->server['REQUEST_METHOD'] ?? 'GET'));
        // Method spoofing for PUT/DELETE from forms.
        if ($rawMethod === 'POST') {
            $spoof = strtoupper((string) ($this->body['_method'] ?? $_POST['_method'] ?? ''));
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                $rawMethod = $spoof;
            }
        }
        $this->method = $rawMethod;

        if ($this->method === 'POST' && $body !== null) {
            $this->body = $body;
        } elseif ($this->method === 'POST') {
            $this->body = $_POST;
        } else {
            $this->body = [];
        }
        // Merge JSON payload (application/json requests).
        $contentType = (string) ($this->server['CONTENT_TYPE'] ?? '');
        if ($this->method !== 'GET' && stripos($contentType, 'application/json') !== false) {
            $json = $this->json();
            if (is_array($json)) {
                $this->body = $json + $this->body;
            }
        }

        $uri = $this->server['REQUEST_URI'] ?? '/';
        $this->path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';
    }

    public static function capture(): self
    {
        return self::$current ??= new self();
    }

    /** During tests / console you can inject a synthetic request. */
    public static function setCurrent(self $r): void
    {
        self::$current = $r;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }
        if (strcasecmp($name, 'Content-Type') === 0 && isset($this->server['CONTENT_TYPE'])) {
            return (string) $this->server['CONTENT_TYPE'];
        }
        return $default;
    }

    /** Laravel-style: does the client expect JSON? (Axios sets X-Requested-With: XMLHttpRequest) */
    public function expectsJson(): bool
    {
        if ($this->header('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }
        $accept = (string) $this->header('Accept', '');
        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    public static function isJsonPreferred(): bool
    {
        return self::capture()->expectsJson();
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body + $this->query;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        $v = $this->input($key);
        return $v !== null && $v !== '';
    }

    public function json(): ?array
    {
        if ($this->jsonCache === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);
            $this->jsonCache = is_array($decoded) ? $decoded : [];
        }
        return $this->jsonCache;
    }

    /** Uploaded file array entry ($_FILES shape) or null. */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /** True when a real file was uploaded for $key (not PHP's empty slot). */
    public function hasFile(string $key): bool
    {
        $file = $this->files[$key] ?? null;
        return $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }
}
