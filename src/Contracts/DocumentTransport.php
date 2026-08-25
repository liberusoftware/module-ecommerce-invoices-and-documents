<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Contracts;

use Liberu\Ecommerce\InvoicesAndDocuments\Data\Rendered;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\RenderModel;
use Liberu\Ecommerce\InvoicesAndDocuments\Data\TransportOutcome;

/** The transport is the host's. This module records what it answered. */
interface DocumentTransport
{
    public function deliver(RenderModel $model, ?Rendered $rendered, string $channel, string $address): TransportOutcome;
}
