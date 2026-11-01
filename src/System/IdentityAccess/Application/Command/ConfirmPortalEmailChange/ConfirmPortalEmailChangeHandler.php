<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\PortalEmailChangedNoticeRequest;
use App\System\IdentityAccess\Application\Service\ActionOtpVerifier;
use App\System\IdentityAccess\Application\Service\PortalOwnerAccounts;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The collision rule (D8) applies here only, once the address is proven,
 * under the lock of the account holding it: an owner who verified it keeps
 * it (`EMAIL_TAKEN`), one who merely had it (copied from a client file)
 * loses it. The previous verified address of the caller receives a notice.
 */
#[AsMessageHandler]
final readonly class ConfirmPortalEmailChangeHandler
{
    private const string LABEL_TIME_ZONE = 'Europe/Paris';

    public function __construct(
        private ActionOtpVerifier $verifier,
        private PortalOwnerAccounts $owners,
        private UserRepositoryInterface $users,
        private NotificationRequesterInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ConfirmPortalEmailChange $command): EmailChangeResult
    {
        $verification = $this->verifier->verify($command->userId, $command->challengeId, $command->code, OtpPurpose::EMAIL_VERIFICATION);

        $failure = match ($verification->result) {
            ActionOtpResult::VERIFIED              => null,
            ActionOtpResult::OTP_INVALID           => EmailChangeResult::OTP_INVALID,
            ActionOtpResult::OTP_EXPIRED           => EmailChangeResult::OTP_EXPIRED,
            ActionOtpResult::OTP_ATTEMPTS_EXCEEDED => EmailChangeResult::OTP_ATTEMPTS_EXCEEDED,
        };
        if (null !== $failure) {
            return $failure;
        }

        $email = $verification->challenge?->targetEmail();
        \assert(null !== $email && null !== $verification->challenge);

        $user   = $this->owners->active($command->userId, lock: true);
        $holder = $this->users->findByEmailAndTypeForUpdate($email, UserType::PORTAL);

        if (null !== $holder && !$holder->id()->equals($user->id())) {
            if ($holder->hasVerifiedEmail()) {
                return EmailChangeResult::EMAIL_TAKEN;
            }

            $holder->releaseUnverifiedEmail();
            $this->users->save($holder);
        }

        $now      = $this->clock->now();
        $previous = $user->hasVerifiedEmail() ? $user->email() : null;
        $user->confirmEmail($email, $now);
        $this->users->save($user);

        if (null !== $previous && 0 !== strcasecmp($previous, $email)) {
            $this->notifications->requestPortalEmailChangedNotice(new PortalEmailChangedNoticeRequest(
                challengeId: $verification->challenge->id()->toString(),
                userId: $user->id()->toString(),
                previousEmail: $previous,
                maskedNewEmail: self::mask($email),
                changedAtLabel: $now->setTimezone(new \DateTimeZone(self::LABEL_TIME_ZONE))->format('d/m/Y à H:i'),
            ));
        }

        return EmailChangeResult::CONFIRMED;
    }

    /** « j***@exemple.fr »: enough to recognise it, not to read it. */
    private static function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }
}
