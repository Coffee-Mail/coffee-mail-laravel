<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel;

use CoffeeMail\CoffeeMail;
use CoffeeMail\Laravel\Http\Middleware\VerifyWebhookSignature;
use CoffeeMail\Laravel\Transport\CoffeeMailTransport;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Mail\MailManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class CoffeeMailServiceProvider extends ServiceProvider
{
    /**
     * Registra os serviços e bindings no container do Laravel.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/coffeemail.php', 'coffeemail');

        $this->app->singleton(CoffeeMail::class, function (): CoffeeMail {
            /** @var ConfigRepository $configRepo */
            $configRepo = $this->app->make('config');

            /** @var array<string, mixed> $config */
            $config = $configRepo->get('coffeemail', []);

            $apiKey = isset($config['api_key']) && is_string($config['api_key'])
                ? $config['api_key']
                : null;

            $locale = isset($config['locale']) && is_string($config['locale'])
                ? $config['locale']
                : 'pt-BR';

            return new CoffeeMail(apiKey: $apiKey, locale: $locale);
        });

        $this->app->alias(CoffeeMail::class, 'coffeemail');
    }

    /**
     * Inicializa os recursos do pacote e registra o driver de e-mail no MailManager.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $configDestination = function_exists('config_path')
                ? config_path('coffeemail.php')
                : $this->app->configPath('coffeemail.php');

            $this->publishes([
                __DIR__ . '/../config/coffeemail.php' => $configDestination,
            ], 'coffeemail-config');
        }

        $this->callAfterResolving(MailManager::class, function (MailManager $mailManager): void {
            $mailManager->extend('coffeemail', function (array $config = []): CoffeeMailTransport {
                /** @var ConfigRepository $configRepo */
                $configRepo = $this->app->make('config');

                /** @var string|null $apiKey */
                $apiKey = $config['api_key'] ?? $configRepo->get('coffeemail.api_key');

                /** @var string $locale */
                $locale = $config['locale'] ?? $configRepo->get('coffeemail.locale', 'pt-BR');

                $client = new CoffeeMail(apiKey: $apiKey, locale: $locale);

                return new CoffeeMailTransport($client);
            });
        });

        if ($this->app->bound('router')) {
            /** @var Router $router */
            $router = $this->app->make('router');
            $router->aliasMiddleware('coffeemail.webhook', VerifyWebhookSignature::class);
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            CoffeeMail::class,
            'coffeemail',
        ];
    }
}
