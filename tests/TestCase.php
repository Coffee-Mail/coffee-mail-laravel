<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Tests;

use CoffeeMail\Laravel\CoffeeMailServiceProvider;
use CoffeeMail\Laravel\Facades\CoffeeMail;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoffeeMailServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'CoffeeMail' => CoffeeMail::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('coffeemail.api_key', 'cm_live_test_api_key_123');
        $app['config']->set('coffeemail.locale', 'pt-BR');
        $app['config']->set('coffeemail.webhook_secret', 'whsec_test_secret_abc');

        $app['config']->set('mail.default', 'coffeemail');
        $app['config']->set('mail.mailers.coffeemail', [
            'transport' => 'coffeemail',
        ]);
    }
}
