<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\ClientPortal\Application\Command\SendPortalInvitation\SendPortalInvitation;
use App\Context\ClientPortal\Application\Query\GetClientPortalStatus\ClientPortalStatus;
use App\Context\ClientPortal\Application\Query\GetClientPortalStatus\GetClientPortalStatus;
use App\Context\ClientPortal\Domain\Exception\ClientNotInvitableException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Inviter au Portal » of the client sheet, and its « Renvoyer l'invitation »
 * (a pending or expired invitation is reissued under the same id) and
 * « Réinviter » (after a withdrawn access) forms: one command, the domain
 * decides which. A client already on the Portal is not invited again.
 */
#[Route('/clients/{id}/portal-invitation', name: 'clinic_clients_portal_invitation_send', methods: ['POST'])]
final class SendPortalInvitationController extends AbstractClientSheetAction
{
    /** French wording of the refusals, by ClientNotInvitableException reason. */
    private const array REFUSALS = [
        ClientNotInvitableException::NO_MOBILE            => 'Ajoutez un numéro de mobile pour inviter ce client',
        ClientNotInvitableException::MOBILE_NOT_SUPPORTED => 'Ce numéro de mobile n’est pas encore pris en charge par le Portal',
        ClientNotInvitableException::NO_EMAIL             => 'Ajoutez une adresse e-mail pour inviter ce client par e-mail',
        ClientNotInvitableException::ARCHIVED             => 'Ce client est archivé',
    ];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly ClinicPermissionGuard $permissions,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);
        $this->permissions->require(ClinicPermissionGuard::MANAGE_CLIENTS);

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $clinicId = $this->clinicId();
        $status   = $this->queryBus->ask(new GetClientPortalStatus($clinicId, $id));
        \assert($status instanceof ClientPortalStatus);

        if (ClientPortalStatus::ACTIVE === $status->state) {
            $this->addFlash('error', 'Ce client a déjà accès au Portal.');

            return $this->backToSheet($id);
        }

        try {
            $this->commandBus->dispatch(new SendPortalInvitation(
                clinicId: $clinicId,
                clientId: $id,
                channel: $request->request->getString('channel', 'sms'),
                actorUserId: $user->id(),
            ));
        } catch (ClientNotInvitableException $refusal) {
            if (ClientNotInvitableException::UNKNOWN_CLIENT === $refusal->reason) {
                throw $this->createNotFoundException('Client introuvable.');
            }

            $this->addFlash('error', self::REFUSALS[$refusal->reason] ?? 'L’invitation n’a pas pu être envoyée.');

            return $this->backToSheet($id);
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'L’invitation n’a pas pu être envoyée : choisissez SMS ou e-mail.');

            return $this->backToSheet($id);
        }

        $resent = \in_array($status->state, [ClientPortalStatus::INVITED, ClientPortalStatus::EXPIRED], true);
        $this->addFlash('success', $resent ? 'Invitation renvoyée' : 'Invitation envoyée');

        return $this->backToSheet($id, 'head');
    }
}
