<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/** What the owner noticed before the visit (questionnaire v1); `none` stands alone. */
enum Symptom: string
{
    case NONE          = 'none';
    case COUGH         = 'cough';
    case VOMITING      = 'vomiting';
    case DIARRHEA      = 'diarrhea';
    case APPETITE_LOSS = 'appetite_loss';
    case ITCHING       = 'itching';
    case LAMENESS      = 'lameness';
}
