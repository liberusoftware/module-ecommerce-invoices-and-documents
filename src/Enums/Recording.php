<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Enums;

enum Recording: string
{
    case Recorded = 'recorded';
    case AlreadyRecorded = 'already_recorded';
    case Refused = 'refused';
}
