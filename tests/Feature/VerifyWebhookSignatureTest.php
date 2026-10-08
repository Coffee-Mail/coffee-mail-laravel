<?php

declare(strict_types=1);

use CoffeeMail\Laravel\Events\WebhookReceived;
use CoffeeMail\Laravel\Http\Middleware\VerifyWebhookSignature;
use CoffeeMail\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::post('/webhooks/coffeemail', fn () => response()->json(['status' => 'ok']))
        ->middleware(VerifyWebhookSignature::class);
});

test('webhook middleware allows authentic requests and dispatches WebhookReceived event', function (): void {
    /** @var TestCase $test */
    $test = $this;
    Event::fake([WebhookReceived::class]);

    $secret = 'whsec_test_secret_abc';
    $payloadData = ['event' => 'email.delivered', 'data' => ['id' => 'eml_123']];
    $rawContent = json_encode($payloadData, JSON_THROW_ON_ERROR);

    $validSignature = hash_hmac('sha256', $rawContent, $secret);

    $response = $test->call(
        method: 'POST',
        uri: '/webhooks/coffeemail',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'HTTP_X_COFFEEMAIL_SIGNATURE' => $validSignature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk()
        ->assertJson(['status' => 'ok']);

    Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event) use ($validSignature): bool {
        return $event->signature === $validSignature
            && ($event->payload['event'] ?? null) === 'email.delivered';
    });
});

test('webhook middleware aborts with 401 when signature is invalid or tampered', function (): void {
    /** @var TestCase $test */
    $test = $this;
    Event::fake([WebhookReceived::class]);

    $rawContent = json_encode(['event' => 'email.bounced'], JSON_THROW_ON_ERROR);

    $response = $test->call(
        method: 'POST',
        uri: '/webhooks/coffeemail',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'HTTP_X_COFFEEMAIL_SIGNATURE' => 'assinatura_adulterada_invalida',
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertStatus(401);
    Event::assertNotDispatched(WebhookReceived::class);
});

test('webhook middleware aborts with 401 when signature header is missing', function (): void {
    /** @var TestCase $test */
    $test = $this;
    Event::fake([WebhookReceived::class]);

    $rawContent = json_encode(['event' => 'email.sent'], JSON_THROW_ON_ERROR);

    $response = $test->call(
        method: 'POST',
        uri: '/webhooks/coffeemail',
        parameters: [],
        cookies: [],
        files: [],
        server: ['CONTENT_TYPE' => 'application/json'],
        content: $rawContent
    );

    $response->assertStatus(401);
    Event::assertNotDispatched(WebhookReceived::class);
});
