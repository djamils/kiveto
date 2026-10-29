<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithGrant;

use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptOutcome;
use App\Context\ClientPortal\Application\Service\PortalInvitationAcceptance;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AcceptPortalInvitationWithGrantHandler
{
    public function __construct(private PortalInvitationAcceptance $acceptance)
    {
    }

    public function __invoke(AcceptPortalInvitationWithGrant $command): AcceptOutcome
    {
        return $this->acceptance->acceptWithGrant($command->invitationId, $command->onboardingToken, $command->termsVersion);
    }
}
