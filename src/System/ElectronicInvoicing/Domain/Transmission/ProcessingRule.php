<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * B2B: an electronic invoice routed to the buyer's platform; B2C: e-reported.
 */
enum ProcessingRule: string
{
    case B2B = 'B2B';
    case B2C = 'B2C';
}
