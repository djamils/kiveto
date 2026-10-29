<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Dispatch;

/**
 * The kind of document whose PDF was stored when it was first sent.
 */
enum SentDocumentKind: string
{
    case INVOICE     = 'INVOICE';
    case CREDIT_NOTE = 'CREDIT_NOTE';
}
