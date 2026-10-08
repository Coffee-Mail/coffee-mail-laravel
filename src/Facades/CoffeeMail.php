<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Facades;

use CoffeeMail\CoffeeMail as CoffeeMailClient;
use CoffeeMail\Resources\Audiences;
use CoffeeMail\Resources\Broadcasts;
use CoffeeMail\Resources\Domains;
use CoffeeMail\Resources\Emails;
use CoffeeMail\Resources\Stats;
use CoffeeMail\Resources\Suppressions;
use CoffeeMail\Resources\Templates;
use CoffeeMail\Resources\Webhooks;
use Illuminate\Support\Facades\Facade;

/**
 * Facade de acesso ergonômico aos recursos do SDK CoffeeMail no Laravel.
 *
 * @property-read Emails $emails
 * @property-read Webhooks $webhooks
 * @property-read Domains $domains
 * @property-read Templates $templates
 * @property-read Audiences $audiences
 * @property-read Broadcasts $broadcasts
 * @property-read Suppressions $suppressions
 * @property-read Stats $stats
 *
 * @method static \CoffeeMail\Http\CoffeeMailResponse<mixed> introspect()
 * @method static void invalidateApiKeyCache()
 * @method static string getApiKey()
 * @method static string getLocale()
 *
 * @see \CoffeeMail\CoffeeMail
 */
final class CoffeeMail extends Facade
{
    /**
     * Obtém o nome registrado do componente no container.
     */
    protected static function getFacadeAccessor(): string
    {
        return CoffeeMailClient::class;
    }
}
