# Changelog

Todas as mudanças relevantes deste projeto são documentadas aqui. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto adota o [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [Não lançado]

## [0.2.0] - Não publicado

Esta versão corrige a verificação de webhooks e **quebra o comportamento da 0.1.0**. A quebra foi aceita porque ainda não havia usuários ativos; a partir daqui volta a regra de só adicionar e depreciar.

### Adicionado

- Config `coffeemail.webhook_tolerance` (`COFFEEMAIL_WEBHOOK_TOLERANCE`, padrão 300 s) para a janela do timestamp.
- `WebhookReceived` ganha `idempotencyKey` e `test`.

### Alterado

- **Quebra:** o middleware `coffeemail.webhook` lê `x-webhook-signature`, `x-webhook-timestamp` e `x-webhook-idempotency-key` e valida com o algoritmo da API (`sha256=` + HMAC-SHA256 de `timestamp.idempotencyKey.corpo`) pelo SDK PHP 0.2.0. A versão anterior lia `x-coffeemail-signature` e rejeitava toda entrega real.
- **Quebra:** exige `coffeemail/coffeemail-php` `^0.2.0`, que também muda a forma dos erros e remove `broadcasts->delete()`, `audiences->addContact()` e `removeContact()` (ver o CHANGELOG do SDK PHP).

## [0.1.0] - 2026-10-08

### Adicionado

- Primeira versão publicada no Packagist.
