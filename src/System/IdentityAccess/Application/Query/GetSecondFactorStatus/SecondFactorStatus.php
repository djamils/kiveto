<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetSecondFactorStatus;

final readonly class SecondFactorStatus
{
    public function __construct(
        public bool $enabled,
        public ?\DateTimeImmutable $enabledAt,
        public int $remainingBackupCodes,
        public int $backupCodeCount,
        public int $trustedDevices,
    ) {
    }
}
