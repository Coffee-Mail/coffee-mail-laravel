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
     * @param array<string, mixed> $payload
     * @param string $signature
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $signature,
    ) {
    }
}
