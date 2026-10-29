<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

final readonly class StaffInvitationRequest
{
    /**
     * @param string $firstName   « Hugo », or '' when unknown
     * @param string $inviterName « Dr Claire Lambert »
     * @param string $roleLabel   « Vétérinaire »
     */
    public function __construct(
        public string $tokenId,
        public string $userId,
        public string $email,
        public string $clinicId,
        public string $firstName,
        public string $inviterName,
        public string $clinicName,
        public string $clinicCity,
        public string $roleLabel,
        public string $activationUrl,
    ) {
    }
}
