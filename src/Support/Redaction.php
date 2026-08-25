<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Support;

use Illuminate\Support\Facades\Config;

final class Redaction
{
    public static function token(): string
    {
        $token = Config::get('invoices-and-documents.redaction_token');

        return is_string($token) && $token !== '' ? $token : 'redacted';
    }

    /**
     * A redacted subject stays distinct per document. Collapsing every subject
     * onto one shared token is the wave 19 defect: it makes two strangers look
     * like the same person to everything downstream.
     */
    public static function subject(string $documentReference): string
    {
        return self::token().':'.$documentReference;
    }
}
