<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\PortalQuestionnaire;
use App\Presentation\Api\Portal\Support\ApiDate;

/**
 * The pre-visit questionnaire of an appointment: `answers` and `updatedAt`
 * (clinic offset) are null until the owner submits it.
 */
final readonly class QuestionnaireView
{
    public function __construct(
        #[ApiProperty(schema: ['type' => 'integer', 'enum' => [1]])]
        public int $version,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['not_started', 'submitted']])]
        public string $status,
        public ?QuestionnaireAnswersView $answers,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $updatedAt,
        public bool $editable,
    ) {
    }

    public static function of(PortalQuestionnaire $questionnaire): self
    {
        return new self(
            $questionnaire->version,
            $questionnaire->status,
            null === $questionnaire->answers ? null : QuestionnaireAnswersView::of($questionnaire->answers),
            null === $questionnaire->updatedAt ? null : ApiDate::formatLocal($questionnaire->updatedAt),
            $questionnaire->editable,
        );
    }
}
