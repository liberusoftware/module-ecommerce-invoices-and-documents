<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\InvoicesAndDocuments\Contracts\DocumentRenderer;
use Liberu\Ecommerce\InvoicesAndDocuments\Contracts\DocumentTransport;
use Liberu\Ecommerce\InvoicesAndDocuments\Contracts\SaleSource;

/**
 * Resolved at the moment of use, so a host rebinding takes effect on the next
 * call. Nothing is bound by default: null means nobody answered, which is not
 * an answer of nothing.
 *
 * `App`/`Config` facades rather than `app()`/`config()`, which live in
 * `laravel/framework` and not in `illuminate/support`.
 */
final class Seams
{
    public static function saleSource(): ?SaleSource
    {
        return self::resolve('invoices-and-documents.seams.sale', SaleSource::class);
    }

    public static function renderer(): ?DocumentRenderer
    {
        return self::resolve('invoices-and-documents.seams.renderer', DocumentRenderer::class);
    }

    public static function transport(): ?DocumentTransport
    {
        return self::resolve('invoices-and-documents.seams.transport', DocumentTransport::class);
    }

    /**
     * @template TContract of object
     *
     * @param  class-string<TContract>  $contract
     * @return TContract|null
     */
    private static function resolve(string $key, string $contract): ?object
    {
        $configured = Config::get($key);

        if ($configured instanceof $contract) {
            return $configured;
        }

        if (is_string($configured) && $configured !== '') {
            $made = App::make($configured);

            return $made instanceof $contract ? $made : null;
        }

        if (! App::bound($contract)) {
            return null;
        }

        $bound = App::make($contract);

        return $bound instanceof $contract ? $bound : null;
    }
}
