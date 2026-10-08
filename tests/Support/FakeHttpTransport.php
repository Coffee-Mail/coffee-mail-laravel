<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Tests\Support;

use CoffeeMail\Http\CoffeeMailResponse;
use CoffeeMail\Http\HttpTransportInterface;

final class FakeHttpTransport implements HttpTransportInterface
{
    /** @var list<array{method: string, path: string, body: array<string, mixed>|list<mixed>|null, query: array<string, mixed>, headers: array<string, string>}> */
    public array $history = [];

    public ?string $lastMethod = null;
    public ?string $lastPath = null;

    /** @var array<string, mixed>|list<mixed>|null */
    public ?array $lastBody = null;

    /** @var array<string, mixed> */
    public array $lastQuery = [];

    /** @var array<string, string> */
    public array $lastHeaders = [];

    /**
     * @param CoffeeMailResponse<mixed>|null $defaultResponse
     */
    public function __construct(
        public ?CoffeeMailResponse $defaultResponse = null,
    ) {
        $this->defaultResponse ??= new CoffeeMailResponse(
            data: ['id' => 'eml_laravel_mock_123', 'status' => 'queued'],
            error: null,
            statusCode: 201,
        );
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return CoffeeMailResponse<mixed>
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        array $headers = [],
    ): CoffeeMailResponse {
        $this->lastMethod = $method;
        $this->lastPath = $path;
        $this->lastBody = $body;
        $this->lastQuery = $query;
        $this->lastHeaders = $headers;

        $this->history[] = [
            'method' => $method,
            'path' => $path,
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ];

        return $this->defaultResponse ?? new CoffeeMailResponse(null, null, 200);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return CoffeeMailResponse<mixed>
     */
    public function get(string $path, array $query = [], array $headers = []): CoffeeMailResponse
    {
        return $this->request('GET', $path, null, $query, $headers);
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return CoffeeMailResponse<mixed>
     */
    public function post(string $path, ?array $body = null, array $query = [], array $headers = []): CoffeeMailResponse
    {
        return $this->request('POST', $path, $body, $query, $headers);
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return CoffeeMailResponse<mixed>
     */
    public function put(string $path, ?array $body = null, array $query = [], array $headers = []): CoffeeMailResponse
    {
        return $this->request('PUT', $path, $body, $query, $headers);
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return CoffeeMailResponse<mixed>
     */
    public function patch(string $path, ?array $body = null, array $query = [], array $headers = []): CoffeeMailResponse
    {
        return $this->request('PATCH', $path, $body, $query, $headers);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return CoffeeMailResponse<mixed>
     */
    public function delete(string $path, array $query = [], array $headers = []): CoffeeMailResponse
    {
        return $this->request('DELETE', $path, null, $query, $headers);
    }

    public function getLocale(): string
    {
        return 'pt-BR';
    }
}
