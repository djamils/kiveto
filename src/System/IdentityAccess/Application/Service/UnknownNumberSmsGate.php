<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Service;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpRequestContext;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitationsInterface;
use App\System\IdentityAccess\Application\Port\UnknownNumberSmsBudgetInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

/**
 * Whether a sign-in code may be texted to a phone (S7a', anti SMS-pumping),
 * for a request and for each resend alike. A phone with an active owner
 * account or a pending invitation (a phone a clinic chose) is known and
 * always texted. Any other spends the budget of unknown numbers, in three
 * layers checked in order — the client's network (/24, /64), the device
 * hint when there is one, then the global cap —; a refused layer stops
 * there (the next ones are not spent) and nothing is texted.
 */
final readonly class UnknownNumberSmsGate
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PendingPortalInvitationsInterface $pendingInvitations,
        private UnknownNumberSmsBudgetInterface $budget,
    ) {
    }

    public function allows(PhoneNumber $phone, ?OtpRequestContext $context): bool
    {
        return $this->isKnown($phone) || $this->spend($context);
    }

    /** False as soon as one layer is exhausted. */
    private function spend(?OtpRequestContext $context): bool
    {
        $network = $context?->networkKey();

        if (null !== $network && !$this->budget->consumeNetwork($network)) {
            return false;
        }

        $device = $context?->deviceId;

        if (null !== $device && !$this->budget->consumeDevice($device)) {
            return false;
        }

        return $this->budget->consumeGlobal();
    }

    private function isKnown(PhoneNumber $phone): bool
    {
        return UserStatus::ACTIVE === $this->users->findByPhoneAndType($phone, UserType::PORTAL)?->status()
            || [] !== $this->pendingInvitations->listForPhone($phone);
    }
}
