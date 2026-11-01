<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\OtpVerification;
use App\Presentation\Api\Portal\Support\ApiDate;
use App\Presentation\Api\Portal\View\OtpVerificationView;
use App\Presentation\Api\Portal\View\PendingInvitationView;
use App\Presentation\Api\Portal\View\SessionTokensView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\IssuePortalSession;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\PortalSessionTokens;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpOutcome;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\VerifyPortalLoginOtp;

/**
 * Maps the outcome decided by IdentityAccess (committed, wrong attempts
 * included) and opens a session for an active owner.
 *
 * @implements ProcessorInterface<OtpVerification, OtpVerificationView>
 */
final readonly class VerifyOtpProcessor implements ProcessorInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OtpVerificationView
    {
        $outcome = $this->commandBus->dispatch(new VerifyPortalLoginOtp($data->challengeId, $data->code));
        \assert($outcome instanceof LoginOtpOutcome);

        return match ($outcome->result) {
            LoginOtpResult::AUTHENTICATED      => $this->authenticated($outcome),
            LoginOtpResult::INVITATION_PENDING => new OtpVerificationView(
                status: 'invitation_pending',
                onboardingToken: $outcome->onboardingToken,
                onboardingTokenExpiresAt: ApiDate::formatNullable($outcome->onboardingExpiresAt),
                invitations: PendingInvitationView::list($outcome->pendingInvitations),
            ),
            LoginOtpResult::REGISTRATION_REQUIRED => new OtpVerificationView(
                status: 'registration_required',
                registrationToken: $outcome->onboardingToken,
                registrationTokenExpiresAt: ApiDate::formatNullable($outcome->onboardingExpiresAt),
            ),
            LoginOtpResult::ACCOUNT_DISABLED      => throw PortalProblemMap::problem(PortalProblemMap::ACCOUNT_DISABLED),
            LoginOtpResult::OTP_INVALID           => throw PortalProblemMap::problem(PortalProblemMap::OTP_INVALID),
            LoginOtpResult::OTP_EXPIRED           => throw PortalProblemMap::problem(PortalProblemMap::OTP_EXPIRED),
            LoginOtpResult::OTP_ATTEMPTS_EXCEEDED => throw PortalProblemMap::problem(PortalProblemMap::OTP_ATTEMPTS_EXCEEDED),
        };
    }

    private function authenticated(LoginOtpOutcome $outcome): OtpVerificationView
    {
        \assert(null !== $outcome->userId);
        $tokens = $this->commandBus->dispatch(new IssuePortalSession($outcome->userId));
        \assert($tokens instanceof PortalSessionTokens);
        $pair = SessionTokensView::of($tokens);

        return new OtpVerificationView(
            status: 'authenticated',
            accessToken: $pair->accessToken,
            accessTokenExpiresAt: $pair->accessTokenExpiresAt,
            refreshToken: $pair->refreshToken,
            refreshTokenExpiresAt: $pair->refreshTokenExpiresAt,
            pendingInvitations: PendingInvitationView::list($outcome->pendingInvitations),
            onboardingToken: $outcome->onboardingToken,
            onboardingTokenExpiresAt: ApiDate::formatNullable($outcome->onboardingExpiresAt),
        );
    }
}
