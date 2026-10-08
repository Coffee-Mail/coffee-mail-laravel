<?php

declare(strict_types=1);

use CoffeeMail\CoffeeMail;
use CoffeeMail\Laravel\Tests\Support\FakeHttpTransport;
use CoffeeMail\Laravel\Tests\Support\OrderConfirmationMailable;
use CoffeeMail\Laravel\Transport\CoffeeMailTransport;
use Illuminate\Support\Facades\Mail;

test('Laravel Mailable dispatches end-to-end via Mail facade through CoffeeMail driver', function (): void {
    $fakeHttp = new FakeHttpTransport();
    $coffeeMailClient = new CoffeeMail('cm_live_test_api_key_123', transport: $fakeHttp);

    // Substitui o transport no MailManager para capturar a saída
    Mail::extend('coffeemail', fn () => new CoffeeMailTransport($coffeeMailClient));

    Mail::to('comprador@gmail.com', 'Comprador Feliz')
        ->cc('copia@minhaloja.com.br')
        ->send(new OrderConfirmationMailable());

    expect($fakeHttp->lastMethod)->toBe('POST')
        ->and($fakeHttp->lastPath)->toBe('/v1/product/emails');

    assert(is_array($fakeHttp->lastBody));
    assert(is_array($fakeHttp->lastHeaders));

    expect($fakeHttp->lastBody['subject'])->toBe('Pedido #456 Confirmado com Sucesso')
        ->and($fakeHttp->lastBody['from'])->toBe([
            'email' => 'pedidos@minhaloja.com.br',
            'name' => 'Minha Loja',
        ])
        ->and($fakeHttp->lastBody['to'])->toBe([
            [
                'email' => 'comprador@gmail.com',
                'name' => 'Comprador Feliz',
            ],
        ])
        ->and($fakeHttp->lastBody['cc'])->toBe([
            [
                'email' => 'copia@minhaloja.com.br',
            ],
        ])
        ->and($fakeHttp->lastBody['html'])->toContain('Seu pedido #456 foi faturado.')
        ->and($fakeHttp->lastHeaders['x-idempotency-key'])->toBe('idem_order_456')
        ->and($fakeHttp->lastBody['attachments'])->toHaveCount(1)
        ->and($fakeHttp->lastBody['attachments'][0]['filename'])->toBe('recibo.pdf')
        ->and($fakeHttp->lastBody['attachments'][0]['content'])->toBe(base64_encode('PDF_RECIBO_BYTES'));
});
