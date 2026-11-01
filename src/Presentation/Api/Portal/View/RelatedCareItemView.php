<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;
use App\Presentation\Api\Portal\Support\ApiDate;

/** The pet's care item of the appointment motif's kind. */
final readonly class RelatedCareItemView
{
    public function __construct(
        public string $kind,
        public string $label,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date'])]
        public string $dueOn,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['booked', 'overdue', 'due_soon', 'up_to_date']])]
        public string $status,
    ) {
    }

    public static function of(OwnerCareItem $item): self
    {
        return new self($item->kind, $item->label, ApiDate::date($item->dueOn), $item->status->value);
    }
}
