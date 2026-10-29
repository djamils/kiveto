<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithGrant\AcceptPortalInvitationWithGrant;
use App\Presentation\Api\Portal\Resource\InvitationGrantAcceptance;
use App\Presentation\Api\Portal\Support\AcceptanceResponder;
use App\Presentation\Api\Portal\View\AcceptedInvitationView;

/**
 * The grant is verified by IdentityAccess, never here.
 *
 * @implements ProcessorInterface<InvitationGrantAcceptance, AcceptedInvitationView>
 */
final readonly class AcceptInvitationWithGrantProcessor implements ProcessorInterface
{
    public function __construct(private AcceptanceResponder $acceptance)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AcceptedInvitationView
    {
        $invitationId = $uriVariables['invitationId'] ?? null;
        \assert(\is_string($invitationId));

        return $this->acceptance->accept(new AcceptPortalInvitationWithGrant($invitationId, $data->onboardingToken, $data->termsVersion));
    }
}
