<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The appointment started, a consultation began from it, or it no longer
 * holds its slot: the questionnaire is frozen. The API answers 422
 * `questionnaire_locked`.
 */
final class QuestionnaireLockedException extends \DomainException
{
    public function __construct(public readonly string $appointmentId)
    {
        parent::__construct('Le questionnaire ne peut plus être modifié.');
    }
}
