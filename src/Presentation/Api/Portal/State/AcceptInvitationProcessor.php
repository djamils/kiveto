<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithCode\AcceptPortalInvitationWithCode;
use App\Presentation\Api\Portal\Resource\InvitationAcceptance;
use App\Presentation\Api\Portal\Support\AcceptanceResponder;
use App\Presentation\Api\Portal\View\AcceptedInvitationView;

/**
 * @implements ProcessorInterface<InvitationAcceptance, AcceptedInvitationView>
 */
final readonly class AcceptInvitationProcessor implements ProcessorInterface
{
    public function __construct(private AcceptanceResponder $acceptance)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AcceptedInvitationView
    {
        return $this->acceptance->accept(new AcceptPortalInvitationWithCode($data->token, $data->challengeId, $data->code, $data->termsVersion));
    }
}
