<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Http\Middleware;

use Closure;
use CoffeeMail\Laravel\Events\WebhookReceived;
use CoffeeMail\Resources\Webhooks;
use CoffeeMail\Webhooks\WebhookVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class VerifyWebhookSignature
{
    private const TEST_ENABLED = 'true';

    /**
     * Valida a assinatura da entrega (headers x-webhook-*, algoritmo da API) e despacha WebhookReceived.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('coffeemail.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new HttpException(
                500,
                'O segredo de webhook (COFFEEMAIL_WEBHOOK_SECRET) não está configurado na aplicação.'
            );
        }

        $signature = $request->header(WebhookVerifier::HEADER_SIGNATURE);

        if (! is_string($signature) || trim($signature) === '') {
            throw new HttpException(401, 'Cabeçalho de assinatura do webhook ausente.');
        }

        $payloadRaw = $request->getContent();
        $verified = Webhooks::verifySignature(
            payload: $payloadRaw,
            secret: $secret,
            headers: $request->headers->all(),
            toleranceSeconds: $this->tolerance(),
        );

        if (! $verified) {
            throw new HttpException(401, 'Assinatura criptográfica do webhook inválida.');
        }

        $decoded = json_decode($payloadRaw, true);
        $idempotencyKey = $request->header(WebhookVerifier::HEADER_IDEMPOTENCY_KEY);

        WebhookReceived::dispatch(
            is_array($decoded) ? $decoded : [],
            $signature,
            is_string($idempotencyKey) ? $idempotencyKey : null,
            $request->header(WebhookVerifier::HEADER_TEST) === self::TEST_ENABLED,
        );

        return $next($request);
    }

    private function tolerance(): int
    {
        $configured = config('coffeemail.webhook_tolerance');

        return is_numeric($configured) ? (int) $configured : WebhookVerifier::DEFAULT_TOLERANCE_SECONDS;
    }
}
