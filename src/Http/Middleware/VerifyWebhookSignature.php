<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Http\Middleware;

use Closure;
use CoffeeMail\Laravel\Events\WebhookReceived;
use CoffeeMail\Resources\Webhooks;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class VerifyWebhookSignature
{
    /**
     * Intercepta a requisição do webhook, valida a assinatura HMAC SHA-256 e despacha evento.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var string|null $secret */
        $secret = config('coffeemail.webhook_secret');

        if (empty($secret)) {
            throw new HttpException(
                500,
                'O segredo de webhook (COFFEEMAIL_WEBHOOK_SECRET) não está configurado na aplicação.'
            );
        }

        $signature = $request->header('x-coffeemail-signature')
            ?? $request->header('coffeemail-signature')
            ?? $request->header('signature');

        if (! is_string($signature) || trim($signature) === '') {
            throw new HttpException(401, 'Cabeçalho de assinatura do webhook ausente.');
        }

        $payloadRaw = $request->getContent();

        if (! Webhooks::verifySignature($payloadRaw, $signature, $secret)) {
            throw new HttpException(401, 'Assinatura criptográfica do webhook inválida.');
        }

        /** @var array<string, mixed> $payloadJson */
        $payloadJson = json_decode($payloadRaw, true) ?? [];

        WebhookReceived::dispatch($payloadJson, $signature);

        return $next($request);
    }
}
