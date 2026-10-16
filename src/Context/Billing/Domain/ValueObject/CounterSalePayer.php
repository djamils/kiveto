<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * Who a counter sale was made to. Every sale at the desk names its client —
 * an existing record, or one created there and then — unless the client
 * refuses to give any contact details: the ticket then carries no name, and
 * the refusal is kept with the sale.
 */
enum CounterSalePayer: string
{
    /** A client already on file at the clinic. */
    case CLIENT = 'CLIENT';

    /** A newcomer whose record was created at the desk for this sale. */
    case NEW_CLIENT = 'NEW_CLIENT';

    /** The client refused to give their contact details. */
    case REFUSED = 'REFUSED';
}
