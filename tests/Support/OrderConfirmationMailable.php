<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Tests\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address as MailableAddress;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

final class OrderConfirmationMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new MailableAddress('pedidos@minhaloja.com.br', 'Minha Loja'),
            subject: 'Pedido #456 Confirmado com Sucesso',
            tags: ['pedidos', 'vendas'],
            metadata: ['order_id' => '456'],
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<h2>Obrigado pela sua compra!</h2><p>Seu pedido #456 foi faturado.</p>',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Idempotency-Key' => 'idem_order_456',
                'X-CoffeeMail-Sandbox' => 'false',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => 'PDF_RECIBO_BYTES', 'recibo.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
