<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/** How the pet does on the treatment to renew, as its owner describes it. */
enum PetCondition: string
{
    case IMPROVED        = 'improved';
    case NO_IMPROVEMENT  = 'no_improvement';
    case ADVERSE_EFFECTS = 'adverse_effects';
}
