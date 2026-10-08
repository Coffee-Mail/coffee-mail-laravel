# CoffeeMail Laravel

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://packagist.org/packages/coffeemail/coffeemail-laravel)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-8892BF.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20|%2011.x%20|%2012.x-FF2D20.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-proprietary-green.svg)](LICENSE)

Driver e conector oficial do **CoffeeMail** para o framework **Laravel**. Permite enviar e-mails transacionais e notificações utilizando a infraestrutura nativa do Laravel (`Mail::to()`, `Mailable`, `Notification`), com suporte a filas assíncronas, gerenciamento de webhooks e integração completa via Facade.

---

## Recursos Principais

* **Zero-Code Migration:** Troque o driver no `.env` sem alterar nenhuma linha de código de e-mail existente.
* **Integração Symfony Mailer:** Compatível com o motor moderno de envio de mensagens do Laravel 10.x, 11.x e 12.x.
* **Suporte Completo a Filas (Queues):** Despacho assíncrono nativo com retries automáticos via `ShouldQueue`.
* **Anexos e Cabeçalhos Customizados:** Mapeamento automático de anexos em base64, idempotência e modo Sandbox.
* **Facade Integrada:** Acesso rápido aos recursos do SDK (`CoffeeMail::templates()`, `CoffeeMail::domains()`, etc.).
* **Middleware de Webhooks:** Validação criptográfica HMAC SHA-256 de webhooks com disparo de eventos do Laravel.

---

## Requisitos

* **PHP:** 8.2 ou superior
* **Laravel:** 10.0, 11.0 ou 12.0
* **Extensões PHP:** `ext-curl`, `ext-json`

---

## Instalação

Instale o pacote via Composer:

```bash
composer require coffeemail/coffeemail-laravel
```

O pacote utiliza o **Laravel Package Discovery**, portanto o Service Provider e a Facade serão registrados automaticamente.

---

## Configuração

### 1. Variáveis de Ambiente (`.env`)

Adicione suas credenciais no arquivo `.env`:

```env
MAIL_MAILER=coffeemail
COFFEEMAIL_API_KEY=cm_live_sua_chave_de_api_aqui
COFFEEMAIL_LOCALE=pt-BR
COFFEEMAIL_WEBHOOK_SECRET=whsec_seu_segredo_de_webhook
```

### 2. Configurar o Driver em `config/mail.php`

Adicione a entrada `coffeemail` no array `mailers` do arquivo `config/mail.php`:

```php
'mailers' => [
    'coffeemail' => [
        'transport' => 'coffeemail',
    ],
    // ... outros mailers
],
```

### 3. Publicar Arquivo de Configuração (Opcional)

Se desejar personalizar a configuração, publique o arquivo `config/coffeemail.php`:

```bash
php artisan vendor:publish --tag="coffeemail-config"
```

---

## Exemplos de Uso

### 1. Envio Básico com a Facade `Mail`

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Seu código de verificação é 849201.', function ($message) {
    $message->to('cliente@gmail.com', 'João da Silva')
        ->subject('Código de Verificação');
});
```

### 2. Utilizando Mailables Nativos do Laravel

Crie um mailable tradicional:

```bash
php artisan make:mail PedidoConfirmadoMail
```

```php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class PedidoConfirmadoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $pedido
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pedido #{$this->pedido['id']} Confirmado!",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pedido-confirmado',
            with: ['pedido' => $this->pedido],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Idempotency-Key' => "order_confirm_{$this->pedido['id']}",
                'X-CoffeeMail-Sandbox' => 'false',
            ],
        );
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
```

Disparando o envio (com fila assíncrona automática):

```php
use App\Mail\PedidoConfirmadoMail;
use Illuminate\Support\Facades\Mail;

Mail::to($user->email)->send(new PedidoConfirmadoMail($pedido));
```

---

### 3. Acesso aos Outros Recursos via Facade `CoffeeMail`

Você pode acessar os recursos de templates, domínios, audiências e estatísticas diretamente via Facade:

```php
use CoffeeMail\Laravel\Facades\CoffeeMail;

// Listar modelos de e-mail cadastrados
[$templates, $error] = CoffeeMail::templates()->list();

// Verificar status de apontamento DNS de um domínio
[$domain, $error] = CoffeeMail::domains()->verify('dom_123');

// Consultar métricas de entrega
[$stats, $error] = CoffeeMail::stats()->get([
    'from' => '2026-01-01',
    'to' => '2026-01-31',
]);
```

---

### 4. Gestão de Webhooks

O pacote fornece o middleware `coffeemail.webhook` para validar automaticamente a assinatura HMAC SHA-256 de eventos enviados pelo CoffeeMail.

#### Registrando a Rota no `routes/api.php`:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/coffeemail', function (Request $request) {
    return response()->json(['received' => true]);
})->middleware('coffeemail.webhook');
```

#### Ouvindo o Evento `WebhookReceived`:

Quando um webhook com assinatura válida é recebido, o evento `CoffeeMail\Laravel\Events\WebhookReceived` é disparado automaticamente.

Registre o listener em `app/Providers/EventServiceProvider.php` ou crie um listener dedicado:

```php
namespace App\Listeners;

use CoffeeMail\Laravel\Events\WebhookReceived;
use Illuminate\Support\Facades\Log;

class ProcessarWebhookCoffeeMail
{
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $tipo = $payload['event'] ?? null;

        if ($tipo === 'email.delivered') {
            Log::info("E-mail entregue com sucesso: {$payload['data']['id']}");
        }

        if ($tipo === 'email.bounced') {
            Log::warning("Hard bounce detectado: {$payload['data']['recipient']}");
        }
    }
}
```

---

## Testando sua Aplicação

Nos testes da sua aplicação Laravel, continue utilizando o `Mail::fake()` nativo do framework:

```php
use App\Mail\PedidoConfirmadoMail;
use Illuminate\Support\Facades\Mail;

test('pedido despacha e-mail de confirmacao', function () {
    Mail::fake();

    // Executa a ação do seu controller ou service
    $response = $this->post('/checkout', [...]);

    Mail::assertSent(PedidoConfirmadoMail::class, function ($mail) {
        return $mail->hasTo('cliente@gmail.com');
    });
});
```

---

## Comandos de Desenvolvimento e Validação

Para rodar os testes e ferramentas de análise estática do próprio pacote:

```bash
# Executar suíte de testes Pest
composer test

# Executar análise estática PHPStan (Nível 8)
composer analyse

# Verificar conformidade PSR-12
composer lint
```

---

## Licença

Proprietário. Todos os direitos reservados à equipe CoffeeMail.
