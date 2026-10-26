<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Dispatch;

/**
 * What the clinic sent the client about an invoice: the invoice itself
 * (« Envoyer ») or a reminder of what is left to pay (« Relancer »).
 */
enum DispatchKind: string
{
    case SEND     = 'SEND';
    case REMINDER = 'REMINDER';
}
