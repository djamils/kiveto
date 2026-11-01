<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** `ongoingTreatment` of the pre-visit questionnaire. */
final class OngoingTreatment
{
    #[ApiProperty(required: true)]
    #[Assert\NotNull(message: 'Indiquez si l\'animal suit un traitement.')]
    public ?bool $has = null;

    #[ApiProperty(schema: ['type' => ['string', 'null'], 'maxLength' => 500])]
    #[Assert\Length(max: 500, maxMessage: 'Le détail du traitement comporte {{ limit }} caractères au plus.')]
    public ?string $details = null;
}
