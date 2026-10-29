<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerProfile;

final readonly class OwnerProfileDetails
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?OwnerAddress $address,
        public string $termsVersion,
        public \DateTimeImmutable $termsAcceptedAt,
    ) {
    }
}
