<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClinicListing\Application\Query\GetClinicListing\OpeningHoursView as ListingOpeningHours;

/** One weekly opening slot: ISO day (1 = Monday), clinic-local `HH:MM` times. */
final readonly class OpeningHoursView
{
    public function __construct(
        #[ApiProperty(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 7])]
        public int $day,
        #[ApiProperty(schema: ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$'])]
        public string $opensAt,
        #[ApiProperty(schema: ['type' => 'string', 'pattern' => '^\d{2}:\d{2}$'])]
        public string $closesAt,
    ) {
    }

    public static function of(ListingOpeningHours $hours): self
    {
        return new self($hours->day, $hours->opensAt, $hours->closesAt);
    }
}
