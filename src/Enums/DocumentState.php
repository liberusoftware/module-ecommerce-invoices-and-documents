<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Enums;

enum DocumentState: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Delivered = 'delivered';
    case Void = 'void';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowed(), true);
    }

    /** @return list<self> */
    public function allowed(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Void],
            self::Issued => [self::Delivered, self::Void],
            self::Delivered => [self::Void],
            self::Void => [],
        };
    }

    /** Issued or delivered: the document exists in the world and its content is frozen. */
    public function isIssued(): bool
    {
        return $this === self::Issued || $this === self::Delivered;
    }
}
