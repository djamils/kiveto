<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerClinics;

final readonly class OwnerClinic
{
    /**
     * @param \DateTimeImmutable $linkedAt the earliest confirmed link to the clinic
     * @param list<string>       $petNames active animals of the linked client file(s)
     */
    public function __construct(
        public string $clinicId,
        public string $clinicName,
        public ?string $city,
        public \DateTimeImmutable $linkedAt,
        public array $petNames,
        /** IANA time zone of the clinic: its clinical instants are shown in it. */
        public string $timeZone,
    ) {
    }
}
