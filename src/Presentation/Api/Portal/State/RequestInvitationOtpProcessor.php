<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Resource\InvitationOtpRequest;
use App\Presentation\Api\Portal\Support\InvitationReader;
use App\Presentation\Api\Portal\Support\PortalRateLimits;
use App\Presentation\Api\Portal\View\OtpChallengeView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;

/**
 * The code goes to the phone the clinic chose, never to one the caller
 * names; it never spends the unknown-number budget.
 *
 * @implements ProcessorInterface<InvitationOtpRequest, OtpChallengeView>
 */
final readonly class RequestInvitationOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private InvitationReader $invitations,
        private CommandBusInterface $commandBus,
        private PortalRateLimits $rateLimits,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OtpChallengeView
    {
        $invitation = $this->invitations->byToken($data->token);

        $this->rateLimits->consumeInvitation($invitation->invitationId);
        $this->rateLimits->consumePhone($invitation->phone);
        $this->rateLimits->consumeIp();

        $issued = $this->commandBus->dispatch(new RequestPortalOtp($invitation->phone, 'invitation', $invitation->invitationId));
        \assert($issued instanceof OtpChallengeIssued);

        return OtpChallengeView::of($issued);
    }
}
