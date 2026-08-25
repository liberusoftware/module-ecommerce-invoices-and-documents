<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Enums;

enum EventKind: string
{
    case Drafted = 'drafted';
    case Issued = 'issued';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case Voided = 'voided';
    case Redacted = 'redacted';
}
