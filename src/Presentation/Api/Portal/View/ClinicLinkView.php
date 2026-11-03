<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/**
 * A clinic the owner is linked to (`confirmed`) or waits for (`pending`:
 * an accepted link suggestion, no link date, no animals, no documents).
 */
final readonly class ClinicLinkView
{
    /**
     * @param list<string> $petNames
     * @param list<string> $tags
     */
    public function __construct(
        public string $clinicId,
        public string $name,
        public ?string $city,
        #[ApiProperty(description: "The clinic's phone as its letterhead records it; null when it has none.")]
        public ?string $phone,
        #[ApiProperty(description: "The clinic's letterhead address; null when the letterhead has no street line or no city.")]
        public ?ClinicAddressView $address,
        /** IANA time zone of the clinic, in which its clinical instants are emitted. */
        public string $timezone,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['pending', 'confirmed']])]
        public string $linkStatus,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $linkedAt,
        public array $petNames,
        public array $tags,
        public int $unreadDocuments,
    ) {
    }
}
