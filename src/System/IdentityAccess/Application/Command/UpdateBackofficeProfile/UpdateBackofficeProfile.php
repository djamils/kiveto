<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\UpdateBackofficeProfile;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateBackofficeProfile implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $displayName,
        public string $timeZone,
        public bool $notifyOnRegistryFailure,
        public bool $notifyOnInvitationExpiry,
    ) {
    }
}
