<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListQuestionnaireSummaries;

final readonly class QuestionnaireSummary
{
    /**
     * @param \DateTimeImmutable $updatedAt in the clinic's time zone
     * @param bool               $mayBite   temperament `may_bite` (« peut mordre » chip)
     */
    public function __construct(
        public string $appointmentId,
        public \DateTimeImmutable $updatedAt,
        public bool $mayBite,
    ) {
    }
}
