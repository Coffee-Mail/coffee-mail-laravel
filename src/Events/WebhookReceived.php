<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<array-key, mixed> $payload corpo da entrega: `event_type`, `webhook_id` e `data`
     * @param string $signature valor do header x-webhook-signature
     * @param string|null $idempotencyKey header x-webhook-idempotency-key; muda a cada tentativa de entrega
     * @param bool $test true em disparos de teste (header x-webhook-test)
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $signature,
        public readonly ?string $idempotencyKey = null,
        public readonly bool $test = false,
    ) {
    }
}
