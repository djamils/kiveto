<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Schema of the per-phone mutex of owner accounts; rows are upserted in SQL
 * by DoctrineUserRepository::lockPortalPhone().
 */
#[ORM\Entity]
#[ORM\Table]
class PortalPhoneLockEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 16)]
        public string $phone,
    ) {
    }
}
