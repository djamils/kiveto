<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SubmitQuestionnaire;

enum SubmitQuestionnaireOutcome: string
{
    case SUBMITTED = 'submitted';
    // Frozen (started, consultation begun, slot no longer held): nothing written but the lock
    case LOCKED = 'locked';
}
