<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptOutcome;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptResult;
use App\Context\ClientPortal\Domain\Exception\TermsOutdatedException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\View\AcceptedInvitationView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\IssuePortalSession;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\PortalSessionTokens;

/**
 * Runs an acceptance command (committed, failed attempts included) and, once
 * accepted, opens a session — the same answer for a first acceptance and
 * for a retry after a lost response.
 */
final readonly class AcceptanceResponder
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PortalAccountViews $accounts,
    ) {
    }

    public function accept(object $command): AcceptedInvitationView
    {
        try {
            $outcome = $this->commandBus->dispatch($command);
        } catch (TermsOutdatedException) {
            throw PortalProblemMap::problem(PortalProblemMap::TERMS_OUTDATED);
        }

        \assert($outcome instanceof AcceptOutcome);

        $code = match ($outcome->result) {
            AcceptResult::ACCEPTED                => null,
            AcceptResult::OTP_INVALID             => PortalProblemMap::OTP_INVALID,
            AcceptResult::OTP_EXPIRED             => PortalProblemMap::OTP_EXPIRED,
            AcceptResult::OTP_ATTEMPTS_EXCEEDED   => PortalProblemMap::OTP_ATTEMPTS_EXCEEDED,
            AcceptResult::ONBOARDING_INVALID      => PortalProblemMap::ONBOARDING_INVALID,
            AcceptResult::INVITATION_INVALID      => PortalProblemMap::INVITATION_INVALID,
            AcceptResult::INVITATION_EXPIRED      => PortalProblemMap::INVITATION_EXPIRED,
            AcceptResult::INVITATION_ALREADY_USED => PortalProblemMap::INVITATION_ALREADY_USED,
            AcceptResult::ACCOUNT_DISABLED        => PortalProblemMap::ACCOUNT_DISABLED,
        };

        if (null !== $code) {
            throw PortalProblemMap::problem($code);
        }

        \assert(null !== $outcome->userId);

        return $this->session($outcome->userId);
    }

    /** A new session of the account, with the account itself (acceptances, registration). */
    public function session(string $userId): AcceptedInvitationView
    {
        $tokens = $this->commandBus->dispatch(new IssuePortalSession($userId));
        \assert($tokens instanceof PortalSessionTokens);

        return new AcceptedInvitationView(
            $tokens->accessToken,
            ApiDate::format($tokens->accessTokenExpiresAt),
            $tokens->refreshToken,
            ApiDate::format($tokens->refreshTokenExpiresAt),
            $this->accounts->of($userId),
        );
    }
}
