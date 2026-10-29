<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Resource\InvitationLookup;
use App\Presentation\Api\Portal\Support\ApiDate;
use App\Presentation\Api\Portal\Support\InvitationReader;
use App\Presentation\Api\Portal\Support\PhoneMasker;
use App\Presentation\Api\Portal\View\InvitationClinicView;
use App\Presentation\Api\Portal\View\InvitationLookupView;

/**
 * @implements ProcessorInterface<InvitationLookup, InvitationLookupView>
 */
final readonly class LookupInvitationProcessor implements ProcessorInterface
{
    public function __construct(private InvitationReader $invitations)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InvitationLookupView
    {
        $invitation = $this->invitations->byToken($data->token);

        return new InvitationLookupView(
            invitationId: $invitation->invitationId,
            clinic: new InvitationClinicView($invitation->clinicId, $invitation->clinicName, $invitation->city),
            email: $invitation->email,
            maskedPhone: PhoneMasker::mask($invitation->phone),
            petNames: $invitation->petNames,
            expiresAt: ApiDate::format($invitation->expiresAt),
        );
    }
}
