<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/** A clinic the owner is linked to (confirmed link). */
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
        /** IANA time zone of the clinic, in which its clinical instants are emitted. */
        public string $timezone,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['confirmed']])]
        public string $linkStatus,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $linkedAt,
        public array $petNames,
        public array $tags,
        public int $unreadDocuments,
    ) {
    }
}
