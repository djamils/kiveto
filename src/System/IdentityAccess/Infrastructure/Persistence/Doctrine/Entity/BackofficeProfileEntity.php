<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the console settings of the Kiveto team; written and read by
 * DbalBackofficeProfileStore.
 */
#[ORM\Entity]
#[ORM\Table]
class BackofficeProfileEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'display_name', type: 'string', length: 80)]
        public string $displayName,
        #[ORM\Column(name: 'time_zone', type: 'string', length: 64)]
        public string $timeZone,
        #[ORM\Column(name: 'notify_registry_failure', type: 'boolean')]
        public bool $notifyRegistryFailure,
        #[ORM\Column(name: 'notify_invitation_expiry', type: 'boolean')]
        public bool $notifyInvitationExpiry,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
