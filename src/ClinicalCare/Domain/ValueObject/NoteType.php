<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\ValueObject;

enum NoteType: string
{
    case ANAMNESIS      = 'ANAMNESIS';
    case CLINICAL_EXAM  = 'CLINICAL_EXAM';
    case DIAGNOSIS      = 'DIAGNOSIS';
    case TREATMENT_PLAN = 'TREATMENT_PLAN';
    case FOLLOWUP       = 'FOLLOWUP';
    case GENERAL        = 'GENERAL';
}
