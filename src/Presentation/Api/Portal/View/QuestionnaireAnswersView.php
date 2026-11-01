<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\QuestionnaireAnswersDetails;
use App\Presentation\Api\Portal\Resource\QuestionnaireUpdate;

/** The answers of a submitted questionnaire, in the shape of its `PUT` body. */
final readonly class QuestionnaireAnswersView
{
    /**
     * @param list<string> $symptoms
     */
    public function __construct(
        #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => QuestionnaireUpdate::SYMPTOMS]])]
        public array $symptoms,
        public OngoingTreatmentView $ongoingTreatment,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => QuestionnaireUpdate::TEMPERAMENTS])]
        public string $temperament,
        public ?string $notes,
    ) {
    }

    public static function of(QuestionnaireAnswersDetails $answers): self
    {
        return new self(
            $answers->symptoms,
            new OngoingTreatmentView($answers->hasOngoingTreatment, $answers->ongoingTreatmentDetails),
            $answers->temperament,
            $answers->notes,
        );
    }
}
