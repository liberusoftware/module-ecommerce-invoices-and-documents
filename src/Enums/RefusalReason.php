<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\InvoicesAndDocuments\Enums;

enum RefusalReason: string
{
    case SaleSourceUnbound = 'sale_source_unbound';
    case SaleNotFound = 'sale_not_found';
    case SaleHasNoLines = 'sale_has_no_lines';
    case MixedCurrencies = 'mixed_currencies';
    case CreditNoteRequiresCorrectedDocument = 'credit_note_requires_corrected_document';
    case StatedTotalDisagreesWithLines = 'stated_total_disagrees_with_lines';
    case SeriesNotFound = 'series_not_found';
    case SeriesRequired = 'series_required';
    case ProformaMayNotUseFiscalSeries = 'proforma_may_not_use_fiscal_series';
    case SeriesIsGapless = 'series_is_gapless';
    case NotIssued = 'not_issued';
    case IllegalTransition = 'illegal_transition';
    case NotCorrectable = 'not_correctable';
    case ExceedsCorrectedDocument = 'exceeds_corrected_document';
    case NoRendererBound = 'no_renderer_bound';
    case RendererDeclined = 'renderer_declined';
    case NoTransportBound = 'no_transport_bound';
    case NoDeliveryAddress = 'no_delivery_address';
    case TransportFailed = 'transport_failed';
    case TransportSuppressed = 'transport_suppressed';
}
