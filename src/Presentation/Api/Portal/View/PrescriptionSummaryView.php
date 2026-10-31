<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/** `renewalStatus` stays null until owner renewal requests exist (slice 6). */
final readonly class PrescriptionSummaryView
{
    public function __construct(
        public string $id,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date'])]
        public ?string $validUntil,
        public bool $renewable,
        #[ApiProperty(schema: ['type' => ['object', 'null']])]
        public ?object $renewalStatus,
    ) {
    }
}
