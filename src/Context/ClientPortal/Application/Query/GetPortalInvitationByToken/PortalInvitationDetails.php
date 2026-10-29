<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken;

final readonly class PortalInvitationDetails
{
    /**
     * @param string       $phone    E.164, unmasked (masking is the API's job)
     * @param list<string> $petNames active animals of the client
     */
    public function __construct(
        public string $invitationId,
        public string $clinicId,
        public string $clinicName,
        public ?string $city,
        public ?string $email,
        public string $phone,
        public array $petNames,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
