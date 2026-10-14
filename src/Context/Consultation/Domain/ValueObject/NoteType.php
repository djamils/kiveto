<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

enum NoteType: string
{
    case ANAMNESIS      = 'ANAMNESIS';
    case CLINICAL_EXAM  = 'CLINICAL_EXAM';
    case DIAGNOSIS      = 'DIAGNOSIS';
    case TREATMENT_PLAN = 'TREATMENT_PLAN';
    case FOLLOWUP       = 'FOLLOWUP';
    case GENERAL        = 'GENERAL';
    /** Dated correction signed after closing; the closed record itself stays as it was. */
    case ADDENDUM = 'ADDENDUM';
}
