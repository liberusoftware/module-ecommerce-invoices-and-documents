<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments;

use Illuminate\Support\ServiceProvider;

/**
 * Installing this package boots nothing: `extra.laravel.providers` is absent on
 * purpose and the host's module manager registers the provider only when the
 * module is named in `MODULES_ENABLED`. It binds no seam.
 */
class InvoicesAndDocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/invoices-and-documents.php', 'invoices-and-documents');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/invoices-and-documents.php' => $this->app->configPath('invoices-and-documents.php'),
            ], 'invoices-and-documents-config');
        }
    }
}
