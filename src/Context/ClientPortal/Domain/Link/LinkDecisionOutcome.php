<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Link;

/** What the clinic answered to a « Rattachement ». */
enum LinkDecisionOutcome: string
{
    case CONFIRMED = 'confirmed';
    case REJECTED  = 'rejected';
}
