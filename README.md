<div align="center">

# ☕ CoffeeMail Laravel

**Integração oficial do CoffeeMail com o Laravel: mail transport para `Mail`/Mailables, facade sobre o SDK PHP e middleware de webhooks.**

[![Packagist](https://img.shields.io/packagist/v/coffeemail/coffeemail-laravel.svg)](https://packagist.org/packages/coffeemail/coffeemail-laravel)
[![PHP](https://img.shields.io/badge/php-%5E8.2-8892BF.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x-FF2D20.svg)](composer.json)
[![CI](https://github.com/Coffee-Mail/coffee-mail-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/Coffee-Mail/coffee-mail-laravel/actions/workflows/ci.yml)
[![Licença](https://img.shields.io/badge/licen%C3%A7a-CoffeeMail%20SDK%20License%20(propriet%C3%A1ria)-lightgrey.svg)](LICENSE)

[Instalação](#-instalação) · [Início rápido](#-início-rápido) · [Configuração](#️-configuração) · [Referência](#-referência) · [Webhooks](#-webhooks) · [Status](#-status-do-projeto)

</div>

---

## 📑 Sumário

- [✨ Visão geral](#-visão-geral)
- [🚦 Status do projeto](#-status-do-projeto)
- [📋 Requisitos](#-requisitos)
- [📦 Instalação](#-instalação)
- [🚀 Início rápido](#-início-rápido)
- [⚙️ Configuração](#️-configuração)
- [🛡️ Tratamento de erros](#️-tratamento-de-erros)
- [📚 Referência](#-referência)
- [🔔 Webhooks](#-webhooks)
- [🔖 Versionamento e releases](#-versionamento-e-releases)
- [🧪 Testes e qualidade](#-testes-e-qualidade)
- [🗂️ Estrutura do repositório](#️-estrutura-do-repositório)
- [🤝 Contribuição](#-contribuição)
- [🔗 Projetos relacionados](#-projetos-relacionados)
- [📄 Licença](#-licença)

## ✨ Visão geral

Pacote Composer `coffeemail/coffeemail-laravel`, construído sobre o SDK PHP [`coffeemail/coffeemail-php`](https://github.com/Coffee-Mail/coffee-mail-sdk-php). Entrega três peças:

| Peça | Classe | O que faz |
| --- | --- | --- |
| Mail transport | `CoffeeMail\Laravel\Transport\CoffeeMailTransport` | Transport do Symfony Mailer registrado no `MailManager` como `coffeemail`. Converte o `Symfony\Component\Mime\Email` montado pelo Laravel em `EmailPayload` e chama `emails->send()` do SDK (`POST /v1/product/emails`). |
| Facade | `CoffeeMail\Laravel\Facades\CoffeeMail` | Acesso ao client `CoffeeMail\CoffeeMail` do SDK, registrado como singleton no container (alias `coffeemail`). |
| Webhooks | `CoffeeMail\Laravel\Http\Middleware\VerifyWebhookSignature` + `CoffeeMail\Laravel\Events\WebhookReceived` | Middleware `coffeemail.webhook` que valida HMAC SHA-256 e dispara um evento Laravel. **Veja a limitação em [Status](#-status-do-projeto).** |

Fluxo de envio:

```mermaid
flowchart LR
    A["Mail::to()->send(Mailable)<br/>Mail::raw() / Notification"] --> B[MailManager<br/>mailer 'coffeemail']
    B --> C[CoffeeMailTransport::doSend]
    C -->|EmailPayload| D["SDK PHP<br/>$client->emails->send()"]
    D -->|"POST /v1/product/emails<br/>Bearer API key"| E[(API CoffeeMail)]
    E -->|"{ data: { id } }"| C
    C -->|setMessageId(id)| B
    E -.->|erro| F[TransportException]
```

O ServiceProvider (`CoffeeMail\Laravel\CoffeeMailServiceProvider`) e o alias `CoffeeMail` são registrados por **package auto-discovery** (`extra.laravel` no `composer.json`).

## 🚦 Status do projeto

- **Publicado no Packagist** como `coffeemail/coffeemail-laravel` **v0.1.0** (2026-10-08), gerado a partir do commit `79f1cb8`. A `main` está à frente da tag: arquivo `LICENSE`, PHPStan nível 9, gate `check:standards` e ajuste interno no transport ainda não foram para uma release.
- **Pré-1.0.** A superfície pública pode receber adições; ver [Versionamento](#-versionamento-e-releases).
- **Middleware de webhooks incompatível com a API atual.** A API CoffeeMail assina webhooks com o cabeçalho `x-webhook-signature`, valor `sha256=<hex>`, calculado sobre `"{x-webhook-timestamp}.{x-webhook-idempotency-key}.{corpo}"`. O middleware deste pacote procura `x-coffeemail-signature`, `coffeemail-signature` ou `signature` e compara com o HMAC do **corpo puro**, sem prefixo. Resultado: entregas reais da API são rejeitadas com `401`. Há um contorno em [Webhooks](#-webhooks).
- **Payload do evento:** a API envia `{ "event_type", "webhook_id", "data" }`; não existe chave `event`.
- **`tags` e `metadata` de Mailables não são mapeados** para o campo `tags` do CoffeeMail. O Laravel os transforma em cabeçalhos `X-Tag` / `X-Metadata-<chave>`, que o transport repassa como cabeçalhos customizados; com várias tags, só a última sobrevive (as chaves de `headers` colidem).
- **Base URL fixa.** O SDK PHP aponta para `https://api.coffeemail.com.br` sem opção de override, e o timeout é o default do SDK (10 s); nenhum dos dois é configurável por este pacote.
- **Dependência do SDK.** `composer.json` aceita `^0.1.0 || 0.1.x-dev || dev-main`. Com `minimum-stability: stable` uma app instala o SDK **v0.1.0** do Packagist. A `main` do SDK tem métodos e correções de endpoint ainda não publicados (marcados com † na [Referência](#-referência)); `$client->senders` só existe nela.
- **Sem fake próprio.** Não há `CoffeeMail::fake()`; nos testes da sua app use `Mail::fake()` do Laravel.

## 📋 Requisitos

| Item | Versão (de `composer.json`) |
| --- | --- |
| PHP | `^8.2` (CI roda 8.2, 8.3 e 8.4) |
| `illuminate/mail`, `illuminate/support` | `^10.0 \|\| ^11.0 \|\| ^12.0` |
| `symfony/mailer` | `^6.2 \|\| ^7.0` |
| `coffeemail/coffeemail-php` | `^0.1.0 \|\| 0.1.x-dev \|\| dev-main` |
| Extensões | `ext-curl` e `ext-json` (exigidas pelo SDK PHP) |

Uma API key da organização (`cm_live_...`), criada no dashboard do CoffeeMail, e um domínio remetente verificado.

## 📦 Instalação

```bash
composer require coffeemail/coffeemail-laravel
```

Publicar o arquivo de configuração (opcional; sem ele o pacote usa o `config/coffeemail.php` interno via `mergeConfigFrom`):

```bash
php artisan vendor:publish --tag="coffeemail-config"
```

## 🚀 Início rápido

1. `.env`:

```dotenv
MAIL_MAILER=coffeemail
MAIL_FROM_ADDRESS=nao-responda@seudominio.com.br
MAIL_FROM_NAME="Minha Loja"
COFFEEMAIL_API_KEY=cm_live_sua_chave
```

2. `config/mail.php`, dentro de `mailers`:

```php
'coffeemail' => [
    'transport' => 'coffeemail',
],
```

3. Enviar:

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Seu código de verificação é 849201.', function ($message): void {
    $message->to('cliente@gmail.com', 'João da Silva')
        ->subject('Código de verificação');
});
```

O `id` devolvido pela API vira o Message-ID do `SentMessage` retornado pelo Laravel.

## ⚙️ Configuração

### `config/coffeemail.php`

| Chave | Env var | Tipo | Default | Uso |
| --- | --- | --- | --- | --- |
| `api_key` | `COFFEEMAIL_API_KEY` | `string\|null` | `null` | API key usada pelo singleton da facade e, por padrão, pelo mailer. Se vazia, o SDK ainda tenta `getenv('COFFEEMAIL_API_KEY')`; sem nenhuma, o construtor do client lança `CoffeeMail\Exceptions\AuthenticationError`. |
| `locale` | `COFFEEMAIL_LOCALE` | `string` | `'pt-BR'` | Enviado como `Accept-Language` e usado nas mensagens de erro do SDK. Suportados pelo SDK: `pt-BR`, `en`. |
| `webhook_secret` | `COFFEEMAIL_WEBHOOK_SECRET` | `string\|null` | `null` | Segredo HMAC do middleware `coffeemail.webhook`. Sem ele, o middleware responde `500`. |

### Mailer em `config/mail.php`

| Chave | Obrigatória | Default | Uso |
| --- | --- | --- | --- |
| `transport` | sim | — | Deve ser `'coffeemail'`. |
| `api_key` | não | `coffeemail.api_key` | Sobrescreve a API key só para este mailer (ex.: uma segunda organização). |
| `locale` | não | `coffeemail.locale` | Sobrescreve o locale só para este mailer. |

```php
'mailers' => [
    'coffeemail' => [
        'transport' => 'coffeemail',
    ],
    'coffeemail-marketing' => [
        'transport' => 'coffeemail',
        'api_key' => env('COFFEEMAIL_MARKETING_API_KEY'),
    ],
],
```

### Cabeçalhos de controle

O transport lê estes cabeçalhos da mensagem e os converte em campos do `EmailPayload`; eles não são repassados como cabeçalhos customizados:

| Cabeçalho na mensagem | Campo no payload | Efeito na requisição |
| --- | --- | --- |
| `X-Idempotency-Key` ou `Idempotency-Key` | `idempotencyKey` | Header HTTP `x-idempotency-key` |
| `X-CoffeeMail-Sandbox` (`true`/`false`) | `isSandbox` | Header HTTP `x-coffeemail-sandbox: true` quando verdadeiro |
| `X-CoffeeMail-Scheduled-At` | `scheduledAt` | Campo `scheduledAt` no corpo |

`From`, `To`, `Cc`, `Bcc`, `Reply-To`, `Subject`, `Content-Type`, `MIME-Version`, `Date` e `Message-ID` são tratados como campos estruturados. Qualquer outro cabeçalho vai em `headers`.

### Mapeamento do e-mail

| Mensagem Laravel/Symfony | Payload CoffeeMail |
| --- | --- |
| Primeiro `From` | `from` (`{ email, name? }`); obrigatório, senão `InvalidArgumentException` |
| `To` | `to` (lista); obrigatório, senão `InvalidArgumentException` |
| `Cc` / `Bcc` | `cc` / `bcc` |
| Primeiro `Reply-To` | `replyTo` (só o endereço; o nome é descartado) |
| Corpo HTML / texto | `html` / `text` |
| Anexos | `attachments[]` com `filename` (fallback `attachment.bin`), `content` em base64 e `contentType` |

## 🛡️ Tratamento de erros

### No envio via `Mail`

O transport converte o envelope `{ data, error }` do SDK em exceções do Symfony Mailer, que o Laravel propaga:

| Situação | Exceção |
| --- | --- |
| API ou rede retornou erro | `Symfony\Component\Mailer\Exception\TransportException` com mensagem `Falha ao despachar e-mail via CoffeeMail: {mensagem} (Status: {status})`, `getCode()` = status HTTP (0 em falha de rede) e `getPrevious()` = exceção tipada do SDK |
| Mensagem sem `From` ou sem `To` | `InvalidArgumentException` |
| Mensagem que não é `Symfony\Component\Mime\Email` | `InvalidArgumentException` |
| Mailer resolvido sem API key | `CoffeeMail\Exceptions\AuthenticationError` (lançada pelo construtor do SDK) |

```php
use CoffeeMail\Exceptions\RateLimitError;
use Symfony\Component\Mailer\Exception\TransportException;

try {
    Mail::to($user)->send(new PedidoConfirmadoMail($pedido));
} catch (TransportException $e) {
    $sdkError = $e->getPrevious();

    if ($sdkError instanceof RateLimitError) {
        $retryAfter = $sdkError->retryAfterSeconds;
    }
}
```

O SDK não faz retry automático. Em Mailables `ShouldQueue`, a exceção falha o job e o retry fica com a configuração da fila do Laravel (`tries`, `backoff`).

### Na facade

Os métodos dos recursos retornam `CoffeeMail\Http\CoffeeMailResponse` e não lançam exceção para erros HTTP. A resposta tem `data`, `error` (`?CoffeeMailException`), `statusCode`, `isSuccess()`, `isError()`, `unwrap()` (devolve `data` ou lança o erro) e `toTuple()`; também aceita desestruturação `[$data, $error] = $response`.

Classes em `CoffeeMail\Exceptions`, todas estendendo `CoffeeMailException` (`status`, `errorCode`, `details`):

| Classe | Status | Quando |
| --- | --- | --- |
| `ValidationError` | 400 | Payload inválido |
| `AuthenticationError` | 401 | API key ausente ou inválida |
| `PaymentRequiredError` | 402 | Plano/cota exige pagamento |
| `ForbiddenError` | 403 | Chave sem permissão para o recurso |
| `NotFoundError` | 404 | Recurso inexistente |
| `ConflictError` | 409 | Conflito de estado ou duplicidade |
| `RateLimitError` | 429 | Limite de taxa; expõe `retryAfterSeconds` (de `Retry-After`) |
| `InternalServerError` | demais | Qualquer outro status de erro (inclusive 422 e 5xx) |
| `NetworkException` | 0 | Falha de cURL ou timeout |

## 📚 Referência

### Acesso ao client

A facade encaminha chamadas de método ao singleton `CoffeeMail\CoffeeMail`. Os recursos são **propriedades** do client, não métodos nem membros estáticos: `CoffeeMail::templates->list()` não funciona (`Undefined constant`). Use a raiz da facade ou o container:

```php
use CoffeeMail\CoffeeMail as CoffeeMailClient;
use CoffeeMail\Laravel\Facades\CoffeeMail;

$client = CoffeeMail::getFacadeRoot();
$client = app(CoffeeMailClient::class);
$client = app('coffeemail');

[$templates, $error] = $client->templates->list();
```

Métodos chamáveis direto pela facade:

| Método | Retorno | Endpoint |
| --- | --- | --- |
| `CoffeeMail::introspect(bool $forceRefresh = false)` | `CoffeeMailResponse` (cache local de 5 min) | `GET /v1/product/auth/me/api-key` |
| `CoffeeMail::invalidateApiKeyCache()` | `void` | — |
| `CoffeeMail::getApiKey()` | `string` | — |
| `CoffeeMail::getLocale()` | `string` | — |
| `CoffeeMail::getTransport()` | `HttpTransportInterface` | — |

As tabelas abaixo refletem o SDK PHP na `main`. † = existe só na `main` do SDK (não publicado na v0.1.0). Prefixo comum dos endpoints: `/v1/product`.

### Mailables

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

final class PedidoConfirmadoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $pedido)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pedido #{$this->pedido['id']} confirmado");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pedido-confirmado', with: ['pedido' => $this->pedido]);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Idempotency-Key' => "order_confirm_{$this->pedido['id']}",
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path('recibos/recibo.pdf'))
                ->as('recibo.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

Mail::to($user->email)->send(new PedidoConfirmadoMail($pedido));
```

Com `ShouldQueue`, `send()` enfileira o Mailable na fila padrão do Laravel; o transport roda no worker. Notificações pelo canal `mail` usam o mailer default, logo passam pelo mesmo transport.

### `emails`

| Método | Endpoint |
| --- | --- |
| `send(array\|EmailPayload $payload)` | `POST /emails` |
| `sendBatch(list<array\|EmailPayload> $items)` | `POST /emails/batch` |
| `get(string $id)` | `GET /emails/{id}` |
| `list(array $query = [])` | `GET /emails` |
| `getEvents(string $id)` | `GET /emails/{id}/events` |
| `getTags()` | `GET /emails/tags` |
| `cancel(string $id)` | `POST /emails/{id}/cancel` |
| `resend(string $id)` | `POST /emails/{id}/resend` |

```php
[$email, $error] = CoffeeMail::getFacadeRoot()->emails->get('eml_123');
```

### `domains`

| Método | Endpoint |
| --- | --- |
| `create(array\|DomainPayload $payload)` | `POST /domains` |
| `list(array $query = [])` | `GET /domains` |
| `get(string $id)` | `GET /domains/{id}` |
| `verify(string $id)` | `POST /domains/{id}/verify` |
| `delete(string $id)` | `DELETE /domains/{id}` |
| `getHealth(string $id)` † | `POST /domains/{id}/health` |
| `getWarmupStatus(string $id)` † | `GET /domains/{id}/warmup` |

```php
[$domain, $error] = CoffeeMail::getFacadeRoot()->domains->verify('dom_123');
```

### `templates`

| Método | Endpoint |
| --- | --- |
| `create(array $payload)` | `POST /templates` |
| `list(array $query = [])` | `GET /templates` |
| `get(string $id)` | `GET /templates/{id}` |
| `update(string $id, array $payload)` | `PATCH /templates/{id}` |
| `delete(string $id)` | `DELETE /templates/{id}` |
| `preview(array $payload)` † | `POST /templates/preview` (na v0.1.0 a assinatura é `preview(string $id, array $variables = [])` → `POST /templates/{id}/preview`) |
| `previewById(string $id, array $variables = [])` † | `GET /templates/{id}` + `POST /templates/preview` |
| `format(array $payload)` † | `POST /templates/format` |
| `testRender(array $payload)` † | `POST /templates/test-render` |
| `testSend(string $id, array $payload)` † | `POST /templates/{id}/test-send` |

```php
[$templates, $error] = CoffeeMail::getFacadeRoot()->templates->list();
```

### `audiences`

| Método | Endpoint |
| --- | --- |
| `create(array $payload)` | `POST /audiences` |
| `list(array $query = [])` | `GET /audiences` |
| `get(string $id)` | `GET /audiences/{id}` |
| `delete(string $id)` | `DELETE /audiences/{id}` |
| `listContacts(string $audienceId, array $query = [])` | `GET /audiences/{id}/contacts` |
| `addContact(string $audienceId, array $payload)` | `POST /audiences/{id}/contacts` |
| `bulkAddContacts(string $audienceId, array $contacts)` | `POST /audiences/{id}/contacts/bulk` |
| `removeContact(string $audienceId, string $contactId)` | `DELETE /audiences/{id}/contacts/{contactId}` |

```php
[$contacts, $error] = CoffeeMail::getFacadeRoot()->audiences->listContacts('aud_123');
```

### `broadcasts`

| Método | Endpoint |
| --- | --- |
| `create(array $payload)` | `POST /broadcasts` |
| `list(array $query = [])` | `GET /broadcasts` |
| `get(string $id)` | `GET /broadcasts/{id}` |
| `send(string $id)` | `POST /broadcasts/{id}/send` |
| `cancel(string $id)` | `POST /broadcasts/{id}/cancel` |
| `delete(string $id)` | Depreciado na `main`: retorna `ValidationError` sem chamada de rede (a API não expõe exclusão); na v0.1.0 chama `DELETE /broadcasts/{id}` |
| `testSend(string $id, array $payload)` † | `POST /broadcasts/{id}/test-send` |

```php
[$broadcast, $error] = CoffeeMail::getFacadeRoot()->broadcasts->send('brd_123');
```

### `suppressions`

| Método | Endpoint |
| --- | --- |
| `list(array $query = [])` | `GET /suppressions` |
| `get(string $email)` | `GET /suppressions/{email}` |
| `create(array $payload)` | `POST /suppressions` |
| `delete(string $email)` | `DELETE /suppressions/{email}` |
| `reactivate(string $id)` † | `POST /suppressions/{id}/reactivate` |
| `bulkCreate(array $suppressions)` † | `POST /suppressions/bulk` |

```php
[$suppressions, $error] = CoffeeMail::getFacadeRoot()->suppressions->list();
```

### `webhooks`

| Método | Endpoint |
| --- | --- |
| `create(array\|WebhookPayload $payload)` | `POST /webhooks` |
| `list(array $query = [])` | `GET /webhooks` |
| `get(string $id)` | `GET /webhooks/{id}` |
| `update(string $id, array $payload)` | `PATCH /webhooks/{id}` |
| `delete(string $id)` | `DELETE /webhooks/{id}` |
| `toggle(string $id, bool $enabled)` | `PATCH /webhooks/{id}` (na v0.1.0: `PATCH /webhooks/{id}/toggle`) |
| `rotateSecret(string $id)` | `POST /webhooks/{id}/rotate-secret` |
| `test(string $id)` | `POST /webhooks/{id}/test` |
| `listDeliveries(string $id, array $query = [])` | `GET /webhooks/{id}/deliveries` |
| `Webhooks::verifySignature(string $payload, string $signature, string $secret)` (estático) | — |

```php
[$deliveries, $error] = CoffeeMail::getFacadeRoot()->webhooks->listDeliveries('wh_123');
```

### `stats`

| Método | Endpoint |
| --- | --- |
| `get(array $query = [])` | `GET /stats` |

```php
[$stats, $error] = CoffeeMail::getFacadeRoot()->stats->get(['from' => '2026-01-01', 'to' => '2026-01-31']);
```

### `senders` †

| Método | Endpoint |
| --- | --- |
| `create(string $email, string $displayName)` | `POST /senders` |
| `list()` | `GET /senders` |
| `verify(string $token)` | `POST /senders/verify` |
| `delete(string $id)` | `DELETE /senders/{id}` |

Detalhes dos payloads de cada recurso: [README do SDK PHP](https://github.com/Coffee-Mail/coffee-mail-sdk-php).

## 🔔 Webhooks

### O que o pacote oferece

O ServiceProvider registra o alias de middleware `coffeemail.webhook` (`VerifyWebhookSignature`). Ele:

1. Lê `coffeemail.webhook_secret`; se vazio, responde `500`.
2. Lê a assinatura de `x-coffeemail-signature`, `coffeemail-signature` ou `signature`; se ausente, `401`.
3. Valida com `CoffeeMail\Resources\Webhooks::verifySignature($corpoBruto, $assinatura, $segredo)`, que compara `hash_hmac('sha256', $corpoBruto, $segredo)` em tempo constante; se inválida, `401`.
4. Despacha `CoffeeMail\Laravel\Events\WebhookReceived` com `payload` (corpo JSON decodificado em array) e `signature`, e segue para a rota.

```php
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/coffeemail', fn () => response()->json(['received' => true]))
    ->middleware('coffeemail.webhook');
```

```php
use CoffeeMail\Laravel\Events\WebhookReceived;

final class ProcessarWebhookCoffeeMail
{
    public function handle(WebhookReceived $event): void
    {
        $tipo = $event->payload['event_type'] ?? null;
        $dados = $event->payload['data'] ?? [];
    }
}
```

Registre a rota fora do grupo `web` (ou exclua-a da verificação CSRF), já que o POST vem de servidor.

### Incompatibilidade com a API atual

Como descrito em [Status](#-status-do-projeto), a API envia:

| Cabeçalho | Conteúdo |
| --- | --- |
| `x-webhook-signature` | `sha256=` + HMAC-SHA256 hex de `"{timestamp}.{idempotencyKey}.{corpo}"` |
| `x-webhook-timestamp` | Unix epoch em segundos |
| `x-webhook-idempotency-key` | 16 caracteres hex, único por entrega |
| `x-webhook-test` | Presente em entregas de teste (`POST /webhooks/{id}/test`) |

O middleware não lê nenhum desses cabeçalhos, então rejeita toda entrega real. Até o pacote ser corrigido, valide na própria aplicação seguindo o esquema da API (a API usa janela de 300 s ao verificar o timestamp):

```php
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyCoffeeMailWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('coffeemail.webhook_secret');
        $signature = (string) $request->header('x-webhook-signature');
        $timestamp = (string) $request->header('x-webhook-timestamp');
        $idempotencyKey = (string) $request->header('x-webhook-idempotency-key');

        $expected = 'sha256=' . hash_hmac('sha256', "{$timestamp}.{$idempotencyKey}.{$request->getContent()}", $secret);

        abort_unless($secret !== '' && ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 401);
        abort_unless(hash_equals($expected, $signature), 401);

        return $next($request);
    }
}
```

Eventos emitidos pela API (`event_type`): `email.queued`, `email.scheduled`, `email.processing`, `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`, `email.failed`, `email.cancelled`, `email.suppressed`, `email.complained`, `email.opened`, `email.clicked`, `email.unsubscribed`.

## 🔖 Versionamento e releases

- SemVer. Versão publicada: **v0.1.0**. A versão vem da tag Git; `composer.json` não tem campo `version`.
- **Compatibilidade retroativa:** o pacote é instalado por clientes; a superfície pública (nome do transport `coffeemail`, alias `coffeemail.webhook`, chaves de config, classes `CoffeeMailServiceProvider`, `CoffeeMailTransport`, `VerifyWebhookSignature`, `WebhookReceived`, facade `CoffeeMail`) só recebe adições e depreciações; nada é renomeado nem removido.
- **Publicação:** push de uma tag `v*` dispara `.github/workflows/release.yml`, que valida o `composer.json`, instala dependências (SDK PHP via repositório VCS), roda `composer check` e cria o GitHub Release com notas geradas automaticamente. O Packagist lê a tag do repositório.
- Não há `CHANGELOG.md`; o histórico está nas GitHub Releases e no `git log`.

## 🧪 Testes e qualidade

### Scripts (`composer.json`)

| Comando | Executa |
| --- | --- |
| `composer test` | `pest` (suítes `Feature` e `Unit`, via Orchestra Testbench) |
| `composer analyse` | `phpstan analyse --memory-limit=512M`, **nível 9**, em `src` e `config` |
| `composer lint` | `phpcs` com PSR-12 em `src`, `config` e `tests` |
| `composer format` | `phpcbf` (corrige o que o PSR-12 permitir) |
| `composer check:standards` | `scripts/check-standards.php`: falha se houver `else`, `elseif`/`else if` ou `switch` em `src` |
| `composer check` | `lint` → `analyse` → `test` → `check:standards` |

### CI

`.github/workflows/ci.yml` roda em push e PR para `main`, com matriz PHP 8.2 / 8.3 / 8.4: `composer validate`, adiciona `coffee-mail-sdk-php` como repositório VCS, `composer update`, depois `lint`, `analyse`, `test` e `check:standards`.

### O que os testes cobrem

- `tests/Unit/CoffeeMailTransportTest.php`: conversão `Email` → payload, múltiplos destinatários, cc/bcc/reply-to, anexos, cabeçalhos de controle e `TransportException` em erro da API.
- `tests/Feature/MailableDispatchTest.php`: Mailable ponta a ponta pelo `Mail`.
- `tests/Feature/ServiceProviderTest.php`: singleton, alias, facade e resolução do mailer.
- `tests/Feature/VerifyWebhookSignatureTest.php`: assinatura válida, inválida e ausente (no esquema próprio do middleware, não no da API).

Os testes usam `tests/Support/FakeHttpTransport.php`, um `HttpTransportInterface` em memória. Ele é de uso interno, não faz parte do autoload de produção.

### Testando a sua aplicação

```php
use App\Mail\PedidoConfirmadoMail;
use Illuminate\Support\Facades\Mail;

test('checkout envia confirmação', function (): void {
    Mail::fake();

    $this->post('/checkout', ['pedido' => 456]);

    Mail::assertSent(PedidoConfirmadoMail::class, fn ($mail) => $mail->hasTo('cliente@gmail.com'));
});
```

## 🗂️ Estrutura do repositório

```text
coffee-mail-laravel/
├── config/coffeemail.php                       # api_key, locale, webhook_secret
├── src/
│   ├── CoffeeMailServiceProvider.php           # singleton do client, driver 'coffeemail', middleware, publish
│   ├── Facades/CoffeeMail.php
│   ├── Transport/CoffeeMailTransport.php       # Symfony AbstractTransport
│   ├── Http/Middleware/VerifyWebhookSignature.php
│   └── Events/WebhookReceived.php
├── tests/
│   ├── Feature/                                # provider, Mailable, middleware
│   ├── Unit/                                   # transport
│   └── Support/                                # FakeHttpTransport, Mailable de teste
├── scripts/check-standards.php
├── .github/workflows/{ci,release}.yml
├── phpstan.neon · phpcs.xml · phpunit.xml.dist
└── composer.json
```

## 🤝 Contribuição

- Rode `composer check` antes de abrir PR; é o mesmo gate da release.
- Código em inglês; mensagens de commit e PR em pt-BR.
- Sem `else`/`elseif`/`switch` em `src` (early return ou `match`), PSR-12, PHPStan nível 9 limpo.
- Toda mudança de superfície pública é aditiva (ver [Versionamento](#-versionamento-e-releases)).
- Localmente, `vendor/coffeemail/coffeemail-php` pode ser um symlink para o checkout do SDK; o CI resolve a versão publicada. Teste contra ela antes de depender de algo que só existe na `main` do SDK.

## 🔗 Projetos relacionados

| Repositório | Papel |
| --- | --- |
| [coffee-mail-api](https://github.com/Coffee-Mail/coffee-mail-api) | Backend: API HTTP, filas, worker de envio, billing |
| [coffee-mail-web](https://github.com/Coffee-Mail/coffee-mail-web) | Landing, documentação pública e dashboard |
| [coffee-mail-sdk-node](https://github.com/Coffee-Mail/coffee-mail-sdk-node) | SDK oficial Node.js/TypeScript (`@coffeemail/node`) |
| [coffee-mail-sdk-python](https://github.com/Coffee-Mail/coffee-mail-sdk-python) | SDK oficial Python (`coffeemail`) |
| [coffee-mail-sdk-php](https://github.com/Coffee-Mail/coffee-mail-sdk-php) | SDK oficial PHP (`coffeemail/coffeemail-php`); base deste pacote |
| [coffee-mail-sdk-java](https://github.com/Coffee-Mail/coffee-mail-sdk-java) | SDK oficial Java (`br.com.coffeemail:coffeemail-java`) |
| **coffee-mail-laravel** (você está aqui) | Integração Laravel (`coffeemail/coffeemail-laravel`): mail transport + facade |
| [coffee-mail-sdk-workbench](https://github.com/Coffee-Mail/coffee-mail-sdk-workbench) | Bancada de validação dos SDKs por spec |

## 📄 Licença

**CoffeeMail SDK License**, licença proprietária ([LICENSE](LICENSE); `composer.json` declara `"license": "proprietary"`). Diferente dos SDKs Node, Python, PHP e Java, que são MIT. Em resumo:

- Copyright da CoffeeMail, todos os direitos reservados. O software é licenciado, não vendido.
- Uso permitido somente mediante acordo de licença comercial escrito e assinado por representante autorizado da CoffeeMail. A cópia em registros públicos (Packagist) serve apenas para integração por clientes autorizados da plataforma.
- Sem autorização prévia e por escrito, é proibido copiar, modificar, mesclar, publicar, distribuir, sublicenciar ou vender; fazer engenharia reversa, descompilar ou desmontar (salvo onde a lei permitir); e remover avisos de propriedade.
- Fornecido "no estado em que se encontra", sem garantias.
- Licenciamento comercial: `licensing@coffeemail.com`.

O texto do `LICENSE` prevalece sobre este resumo. A tag publicada v0.1.0 não contém o arquivo `LICENSE`; ele foi adicionado depois, na `main`.
