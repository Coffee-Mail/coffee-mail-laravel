<?php

declare(strict_types=1);

use CoffeeMail\CoffeeMail;
use CoffeeMail\Laravel\Facades\CoffeeMail as CoffeeMailFacade;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

test('service provider binds CoffeeMail singleton and alias in service container', function (): void {
    $client = app(CoffeeMail::class);
    $aliasClient = app('coffeemail');

    expect($client)->toBeInstanceOf(CoffeeMail::class)
        ->and($aliasClient)->toBeInstanceOf(CoffeeMail::class)
        ->and($client)->toBe($aliasClient)
        ->and($client->getApiKey())->toBe('cm_live_test_api_key_123');
});

test('facade resolves methods on underlying CoffeeMail SDK client', function (): void {
    expect(CoffeeMailFacade::getApiKey())->toBe('cm_live_test_api_key_123')
        ->and(CoffeeMailFacade::getLocale())->toBe('pt-BR');
});

test('mail manager successfully resolves coffeemail mailer driver', function (): void {
    $mailer = Mail::mailer('coffeemail');

    expect($mailer)->toBeInstanceOf(Mailer::class);
});

test('facade exposes every SDK resource as a property, exactly as the README shows', function (): void {
    $recursos = [
        'emails' => \CoffeeMail\Resources\Emails::class,
        'webhooks' => \CoffeeMail\Resources\Webhooks::class,
        'domains' => \CoffeeMail\Resources\Domains::class,
        'templates' => \CoffeeMail\Resources\Templates::class,
        'audiences' => \CoffeeMail\Resources\Audiences::class,
        'broadcasts' => \CoffeeMail\Resources\Broadcasts::class,
        'suppressions' => \CoffeeMail\Resources\Suppressions::class,
        'senders' => \CoffeeMail\Resources\Senders::class,
        'stats' => \CoffeeMail\Resources\Stats::class,
    ];

    $client = app(CoffeeMail::class);

    foreach ($recursos as $propriedade => $classe) {
        expect($client->{$propriedade})->toBeInstanceOf($classe);
    }
});

test('facade resources are properties, not methods — calling them would fatal', function (): void {
    $client = app(CoffeeMail::class);

    expect(method_exists($client, 'templates'))->toBeFalse()
        ->and(method_exists($client, 'domains'))->toBeFalse()
        ->and(property_exists($client, 'templates'))->toBeTrue()
        ->and(property_exists($client, 'domains'))->toBeTrue();
});
