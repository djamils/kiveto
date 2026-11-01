<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerClinics;

final readonly class OwnerClinic
{
    public const string CONFIRMED = 'confirmed';
    public const string PENDING   = 'pending';

    /**
     * @param ?\DateTimeImmutable $linkedAt   the earliest confirmed link to the clinic; null while only pending
     * @param list<string>        $petNames   active animals of the confirmed-linked client file(s); none while pending
     * @param string              $linkStatus confirmed (a confirmed link wins) | pending
     */
    public function __construct(
        public string $clinicId,
        public string $clinicName,
        public ?string $city,
        public ?\DateTimeImmutable $linkedAt,
        public array $petNames,
        /** IANA time zone of the clinic: its clinical instants are shown in it. */
        public string $timeZone,
        public string $linkStatus = self::CONFIRMED,
    ) {
    }
}
