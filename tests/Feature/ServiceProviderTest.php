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
