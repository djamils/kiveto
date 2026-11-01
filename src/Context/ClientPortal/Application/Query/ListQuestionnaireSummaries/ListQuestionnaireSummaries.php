<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListQuestionnaireSummaries;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The agenda badge: which of the listed appointments have a submitted
 * questionnaire (one read for a whole range). Answers
 * `list<QuestionnaireSummary>`; closed questionnaires are left out.
 */
final readonly class ListQuestionnaireSummaries implements QueryInterface
{
    /**
     * @param list<string> $appointmentIds
     */
    public function __construct(
        public string $clinicId,
        public array $appointmentIds,
    ) {
    }
}
