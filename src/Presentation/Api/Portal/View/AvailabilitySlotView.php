<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

final readonly class AvailabilitySlotView
{
    public function __construct(
        /** With the clinic's offset. */
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $startAt,
    ) {
    }
}
