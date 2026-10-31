<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\HomeCareItem;
use App\Presentation\Api\Portal\Support\ApiDate;

/** A care item of the home, with the pet it is for. */
final readonly class HomeCareItemView
{
    public function __construct(
        public PetReferenceView $pet,
        public string $kind,
        public string $label,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $lastDoneOn,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $dueOn,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['overdue', 'due_soon', 'up_to_date']])]
        public string $status,
    ) {
    }

    public static function of(HomeCareItem $item): self
    {
        return new self(
            new PetReferenceView($item->petId, $item->petName),
            $item->care->kind,
            $item->care->label,
            ApiDate::date($item->care->lastDoneOn),
            ApiDate::date($item->care->dueOn),
            $item->care->status->value,
        );
    }
}
