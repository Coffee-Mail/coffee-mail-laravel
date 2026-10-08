<?php

declare(strict_types=1);

use CoffeeMail\CoffeeMail;
use CoffeeMail\Exceptions\ValidationError;
use CoffeeMail\Http\CoffeeMailResponse;
use CoffeeMail\Laravel\Tests\Support\FakeHttpTransport;
use CoffeeMail\Laravel\Transport\CoffeeMailTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

test('transport converts Symfony Email to CoffeeMail payload and dispatches to API', function (): void {
    $mockTransport = new FakeHttpTransport();
    $client = new CoffeeMail('cm_live_test', transport: $mockTransport);
    $transport = new CoffeeMailTransport($client);

    $email = (new Email())
        ->from(new Address('contato@empresa.com.br', 'Minha Empresa'))
        ->to(new Address('cliente@gmail.com', 'João da Silva'))
        ->subject('Pedido #999 Aprovado')
        ->html('<h1>Seu pedido foi confirmado!</h1>')
        ->text('Seu pedido foi confirmado!');

    $sentMessage = $transport->send($email);

    expect($mockTransport->lastMethod)->toBe('POST')
        ->and($mockTransport->lastPath)->toBe('/v1/product/emails');

    assert(is_array($mockTransport->lastBody));
    expect($mockTransport->lastBody['subject'])->toBe('Pedido #999 Aprovado')
        ->and($mockTransport->lastBody['from'])->toBe([
            'email' => 'contato@empresa.com.br',
            'name' => 'Minha Empresa',
        ])
        ->and($mockTransport->lastBody['to'])->toBe([
            [
                'email' => 'cliente@gmail.com',
                'name' => 'João da Silva',
            ],
        ])
        ->and($mockTransport->lastBody['html'])->toBe('<h1>Seu pedido foi confirmado!</h1>')
        ->and($mockTransport->lastBody['text'])->toBe('Seu pedido foi confirmado!')
        ->and($sentMessage?->getMessageId())->toBe('eml_laravel_mock_123');
});

test('transport handles multiple recipients, cc, bcc, reply-to and attachments', function (): void {
    $mockTransport = new FakeHttpTransport();
    $client = new CoffeeMail('cm_live_test', transport: $mockTransport);
    $transport = new CoffeeMailTransport($client);

    $email = (new Email())
        ->from('sistema@empresa.com')
        ->to('dest1@gmail.com', 'dest2@gmail.com')
        ->cc('gerente@empresa.com')
        ->bcc('auditoria@empresa.com')
        ->replyTo('suporte@empresa.com')
        ->subject('Relatório Mensal')
        ->html('<p>Segue anexo relatório.</p>')
        ->addPart(new DataPart('CONTEUDO_DO_PDF_BRUTO', 'relatorio.pdf', 'application/pdf'));

    $transport->send($email);

    assert(is_array($mockTransport->lastBody));
    expect($mockTransport->lastBody['to'])->toHaveCount(2)
        ->and($mockTransport->lastBody['cc'])->toBe([['email' => 'gerente@empresa.com']])
        ->and($mockTransport->lastBody['bcc'])->toBe([['email' => 'auditoria@empresa.com']])
        ->and($mockTransport->lastBody['replyTo'])->toBe(['email' => 'suporte@empresa.com'])
        ->and($mockTransport->lastBody['attachments'])->toHaveCount(1);

    assert(is_array($mockTransport->lastBody['attachments'][0]));
    expect($mockTransport->lastBody['attachments'][0]['filename'])->toBe('relatorio.pdf')
        ->and($mockTransport->lastBody['attachments'][0]['contentType'])->toBe('application/pdf')
        ->and($mockTransport->lastBody['attachments'][0]['content'])->toBe(base64_encode('CONTEUDO_DO_PDF_BRUTO'));
});

test('transport extracts custom headers, idempotency key and sandbox flags', function (): void {
    $mockTransport = new FakeHttpTransport();
    $client = new CoffeeMail('cm_live_test', transport: $mockTransport);
    $transport = new CoffeeMailTransport($client);

    $email = (new Email())
        ->from('notificacoes@app.com')
        ->to('usuario@gmail.com')
        ->subject('Alerta')
        ->text('Mensagem de alerta');

    $email->getHeaders()->addTextHeader('X-Idempotency-Key', 'idem_laravel_777');
    $email->getHeaders()->addTextHeader('X-CoffeeMail-Sandbox', 'true');
    $email->getHeaders()->addTextHeader('X-Custom-Tracking-Id', 'trk_999');

    $transport->send($email);

    assert(is_array($mockTransport->lastHeaders));
    assert(is_array($mockTransport->lastBody));

    expect($mockTransport->lastHeaders['x-idempotency-key'])->toBe('idem_laravel_777')
        ->and($mockTransport->lastHeaders['x-coffeemail-sandbox'])->toBe('true')
        ->and($mockTransport->lastBody['headers'])->toBe(['X-Custom-Tracking-Id' => 'trk_999']);
});

test('transport throws Symfony TransportException when CoffeeMail API returns an error', function (): void {
    $mockTransport = new FakeHttpTransport(
        defaultResponse: new CoffeeMailResponse(
            data: null,
            error: new ValidationError('Domínio não verificado ou inválido'),
            statusCode: 422,
        )
    );

    $client = new CoffeeMail('cm_live_test', transport: $mockTransport);
    $transport = new CoffeeMailTransport($client);

    $email = (new Email())
        ->from('invalido@naoverificado.com')
        ->to('usuario@gmail.com')
        ->subject('Teste Falha')
        ->text('Corpo teste');

    expect(fn () => $transport->send($email))
        ->toThrow(TransportException::class, 'Domínio não verificado ou inválido');
});
