<?php

declare(strict_types=1);

use CoffeeMail\Laravel\Events\WebhookReceived;
use CoffeeMail\Laravel\Http\Middleware\VerifyWebhookSignature;
use CoffeeMail\Laravel\Tests\Support\SignedWebhook;
use CoffeeMail\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('coffeemail.webhook_secret', SignedWebhook::SECRET);
    Route::post('/webhooks/coffeemail', fn () => response()->json(['status' => 'ok']))
        ->middleware(VerifyWebhookSignature::class);
});

test('webhook middleware accepts real API signatures and dispatches WebhookReceived', function (): void {
    /** @var TestCase $test */
    $test = $this;
    Event::fake([WebhookReceived::class]);

    $body = '{"event_type":"email.delivered","webhook_id":"wh_1","data":{"messageId":"m1"}}';
    $server = SignedWebhook::server($body, time(), ['HTTP_X_WEBHOOK_TEST' => 'true']);

    SignedWebhook::post($test, $body, $server)->assertOk()->assertJson(['status' => 'ok']);

    Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event) use ($server): bool {
        return $event->signature === $server['HTTP_X_WEBHOOK_SIGNATURE']
            && ($event->payload['event_type'] ?? null) === 'email.delivered'
            && $event->idempotencyKey === 'ed4e7012eb856296'
            && $event->test;
    });
});

test('webhook middleware rejects tampered bodies, wrong secrets and the legacy header', function (): void {
    /** @var TestCase $test */
    $test = $this;
    Event::fake([WebhookReceived::class]);

    $body = '{"event_type":"email.bounced","webhook_id":"wh_1","data":{}}';
    $legacy = [
        'HTTP_X_COFFEEMAIL_SIGNATURE' => hash_hmac('sha256', $body, SignedWebhook::SECRET),
        'CONTENT_TYPE' => 'application/json',
    ];

    SignedWebhook::post($test, $body . ' ', SignedWebhook::server($body, time()))->assertStatus(401);
    SignedWebhook::post($test, $body, $legacy)->assertStatus(401);

    config()->set('coffeemail.webhook_secret', 'whsec_outro');
    SignedWebhook::post($test, $body, SignedWebhook::server($body, time()))->assertStatus(401);

    Event::assertNotDispatched(WebhookReceived::class);
});

test('webhook middleware enforces the configurable timestamp tolerance', function (): void {
    /** @var TestCase $test */
    $test = $this;
    Event::fake([WebhookReceived::class]);

    $body = '{"event_type":"email.sent","webhook_id":"wh_1","data":{}}';

    SignedWebhook::post($test, $body, SignedWebhook::server($body, time() - 301))->assertStatus(401);

    config()->set('coffeemail.webhook_tolerance', 600);
    SignedWebhook::post($test, $body, SignedWebhook::server($body, time() - 301))->assertOk();

    config()->set('coffeemail.webhook_tolerance', 10);
    SignedWebhook::post($test, $body, SignedWebhook::server($body, time() - 30))->assertStatus(401);

    Event::assertDispatchedTimes(WebhookReceived::class, 1);
});

test('webhook middleware answers 401 without signature and 500 without secret', function (): void {
    /** @var TestCase $test */
    $test = $this;
    Event::fake([WebhookReceived::class]);

    $body = '{"event_type":"email.sent"}';

    SignedWebhook::post($test, $body, ['CONTENT_TYPE' => 'application/json'])->assertStatus(401);

    config()->set('coffeemail.webhook_secret', '');
    SignedWebhook::post($test, $body, SignedWebhook::server($body, time()))->assertStatus(500);

    Event::assertNotDispatched(WebhookReceived::class);
});
