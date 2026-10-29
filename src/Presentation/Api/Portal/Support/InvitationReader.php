<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken\GetPortalInvitationByToken;
use App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken\PortalInvitationDetails;
use App\Context\ClientPortal\Domain\Exception\InvitationAlreadyUsedException;
use App\Context\ClientPortal\Domain\Exception\InvitationExpiredException;
use App\Context\ClientPortal\Domain\Exception\InvitationInvalidException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Shared\Application\Bus\QueryBusInterface;

/** Reads an invitation by its token, its refusals as the API's errors. */
final readonly class InvitationReader
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function byToken(string $token): PortalInvitationDetails
    {
        try {
            $invitation = $this->queryBus->ask(new GetPortalInvitationByToken($token));
        } catch (InvitationInvalidException) {
            throw PortalProblemMap::problem(PortalProblemMap::INVITATION_INVALID);
        } catch (InvitationExpiredException) {
            throw PortalProblemMap::problem(PortalProblemMap::INVITATION_EXPIRED);
        } catch (InvitationAlreadyUsedException) {
            throw PortalProblemMap::problem(PortalProblemMap::INVITATION_ALREADY_USED);
        }

        \assert($invitation instanceof PortalInvitationDetails);

        return $invitation;
    }
}
