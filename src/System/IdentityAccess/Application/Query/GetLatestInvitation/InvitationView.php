<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetLatestInvitation;

final readonly class InvitationView
{
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $sentAt,
        public \DateTimeImmutable $expiresAt,
        public bool $expired,
    ) {
    }
}
