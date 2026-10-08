<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | CoffeeMail API Key
    |--------------------------------------------------------------------------
    | Chave de autenticação da organização para acesso aos serviços da API.
    | Pode ser obtida diretamente no dashboard web do CoffeeMail.
    */
    'api_key' => env('COFFEEMAIL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Localização Padrão (i18n)
    |--------------------------------------------------------------------------
    | Idioma utilizado para retorno de mensagens de erro formatadas.
    | Opções suportadas: 'pt-BR' ou 'en'.
    */
    'locale' => env('COFFEEMAIL_LOCALE', 'pt-BR'),

    /*
    |--------------------------------------------------------------------------
    | Segredo do Webhook (HMAC SHA-256)
    |--------------------------------------------------------------------------
    | Segredo criptográfico utilizado pelo middleware nativo para validar a
    | autenticidade das notificações de eventos recebidas da plataforma.
    */
    'webhook_secret' => env('COFFEEMAIL_WEBHOOK_SECRET'),
];
