<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the back-office second factors; rows are written and read in
 * SQL by DbalSecondFactorRepository. The key is encrypted (TotpSecretCipher),
 * the backup codes are digests.
 */
#[ORM\Entity]
#[ORM\Table]
class SecondFactorEntity
{
    /**
     * @param list<array{hash: string, used_at: ?string}> $backupCodes
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'secret_ciphertext', type: 'string', length: 255)]
        public string $secretCiphertext,
        #[ORM\Column(name: 'enabled_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $enabledAt,
        #[ORM\Column(name: 'last_used_step', type: 'bigint', nullable: true)]
        public ?string $lastUsedStep,
        #[ORM\Column(name: 'backup_codes', type: 'json')]
        public array $backupCodes,
    ) {
    }
}
