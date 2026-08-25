<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Enums;

enum DocumentKind: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Receipt = 'receipt';
    case Proforma = 'proforma';

    /** A fiscal kind must be filed under a series; a proforma is a quotation with a document's shape. */
    public function isFiscal(): bool
    {
        return $this !== self::Proforma;
    }

    public function correctsAnother(): bool
    {
        return $this === self::CreditNote;
    }
}
