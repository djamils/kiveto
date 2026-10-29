<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithCode;

use App\Context\ClientPortal\Application\Command\AcceptPortalInvitation\AcceptOutcome;
use App\Context\ClientPortal\Application\Service\PortalInvitationAcceptance;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AcceptPortalInvitationWithCodeHandler
{
    public function __construct(private PortalInvitationAcceptance $acceptance)
    {
    }

    public function __invoke(AcceptPortalInvitationWithCode $command): AcceptOutcome
    {
        return $this->acceptance->acceptWithCode($command->token, $command->challengeId, $command->code, $command->termsVersion);
    }
}
