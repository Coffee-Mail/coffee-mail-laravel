<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Tests\Support;

use CoffeeMail\Laravel\Tests\TestCase;
use CoffeeMail\Webhooks\WebhookVerifier;
use Illuminate\Testing\TestResponse;

final class SignedWebhook
{
    public const SECRET = 'whsec_test_secret_abc';
    public const IDEMPOTENCY_KEY = 'ed4e7012eb856296';

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public static function server(string $body, int $timestamp, array $overrides = []): array
    {
        $signature = WebhookVerifier::sign($body, self::SECRET, (string) $timestamp, self::IDEMPOTENCY_KEY);

        return array_merge([
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_IDEMPOTENCY_KEY' => self::IDEMPOTENCY_KEY,
            'CONTENT_TYPE' => 'application/json',
        ], $overrides);
    }

    /**
     * @param array<string, string> $server
     */
    public static function post(TestCase $test, string $body, array $server): TestResponse
    {
        return $test->call(
            method: 'POST',
            uri: '/webhooks/coffeemail',
            parameters: [],
            cookies: [],
            files: [],
            server: $server,
            content: $body,
        );
    }
}
