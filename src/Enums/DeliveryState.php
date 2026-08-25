<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Enums;

enum DeliveryState: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Suppressed = 'suppressed';
}
