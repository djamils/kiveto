<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Mutex rows of the Portal locks (`account:{userId}`,
 * `pet-changes:{clinicId}:{petId}`), written in SQL by DbalPortalLock.
 */
#[ORM\Entity]
#[ORM\Table(name: 'client_portal__locks')]
class PortalLockEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'lock_key', type: 'string', length: 100)]
        public string $lockKey,
    ) {
    }
}
