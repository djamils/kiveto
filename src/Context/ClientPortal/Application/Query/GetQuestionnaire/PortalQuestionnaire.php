<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetQuestionnaire;

final readonly class PortalQuestionnaire
{
    public const string NOT_STARTED = 'not_started';
    public const string SUBMITTED   = 'submitted';
    public const string CLOSED      = 'closed';

    /**
     * @param string              $status    not_started|submitted|closed (closed: the appointment was cancelled or declined, or the account deleted)
     * @param ?\DateTimeImmutable $updatedAt in the clinic's time zone
     * @param bool                $editable  whether the owner can still write it (S6b)
     */
    public function __construct(
        public string $appointmentId,
        public int $version,
        public string $status,
        public ?QuestionnaireAnswersDetails $answers,
        public ?\DateTimeImmutable $updatedAt,
        public bool $editable,
        public bool $mayBite,
    ) {
    }
}
