<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/** How the pet behaves at the clinic, as its owner describes it (questionnaire v1). */
enum Temperament: string
{
    case CALM     = 'calm';
    case STRESSED = 'stressed';
    case MAY_BITE = 'may_bite';
}
