<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Command\ResolvePortalRequest\ResolvePortalRequest;
use App\Context\ClientPortal\Application\Query\GetPortalRequest\GetPortalRequest;
use App\Context\ClientPortal\Application\Query\GetPortalRequest\PortalRequestDetails;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestNotFoundException;
use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * « Traiter » (`done`) or « Refuser » (`rejected`) a Portal request, with an
 * optional message to the owner and, for a renewal, the prescription written
 * for it. A change request needs `manage_animals`, a renewal
 * `create_prescription`. Back on the request with a flash; a colleague who
 * answered first wins (« Demande déjà traitée par … »).
 */
#[Route('/portal-requests/{id}/resolve', name: 'clinic_portal_requests_resolve', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class ResolvePortalRequestController extends AbstractSheetAction
{
    protected const string CSRF_ID = 'portal_request';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly ClinicPermissionGuard $permissions,
        private readonly StaffNameResolver $staffNameResolver,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);
        $this->permissions->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->clinicId();
        $details  = $this->queryBus->ask(new GetPortalRequest($clinicId, $id));

        if (!$details instanceof PortalRequestDetails) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $isRenewal = 'prescription_renewal' === $details->kind;

        if ($isRenewal && !$this->permissions->has(ClinicPermissionGuard::CREATE_PRESCRIPTION)) {
            $this->addFlash('error', 'Seul un membre habilité à prescrire peut répondre à une demande de renouvellement.');

            return $this->back($id);
        }

        $outcome = $request->request->getString('outcome');

        if (!\in_array($outcome, ['done', 'rejected'], true)) {
            $this->addFlash('error', 'Choisissez « Traiter » ou « Refuser ».');

            return $this->back($id);
        }

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        try {
            $status = $this->commandBus->dispatch(new ResolvePortalRequest(
                clinicId: $clinicId,
                requestId: $id,
                outcome: $outcome,
                actorUserId: $user->id(),
                actorName: $this->staffNameResolver->resolve($clinicId, $user),
                message: $this->trimmed($request, 'message'),
                resultingPrescriptionId: $isRenewal && 'done' === $outcome ? $this->trimmed($request, 'resulting_prescription_id') : null,
            ));
        } catch (PortalRequestNotFoundException) {
            throw $this->createNotFoundException('Demande introuvable.');
        } catch (PortalRequestAlreadyResolvedException $exception) {
            // « Demande déjà traitée par … » / « Demande déjà close. »
            $this->addFlash('error', $exception->getMessage());

            return $this->back($id);
        } catch (InvalidPortalRequestException $exception) {
            // Its French reason (a message too long, a prescription that does not fit).
            $this->addFlash('error', $exception->getMessage());

            return $this->back($id);
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'La réponse n’a pas pu être enregistrée.');

            return $this->back($id);
        }

        $this->addFlash('success', match ($status) {
            'done'     => 'Demande traitée — le client est prévenu.',
            'rejected' => 'Demande refusée — le client est prévenu.',
            default    => 'Demande close : l’animal n’est plus suivi.',
        });

        return $this->back($id);
    }

    private function back(string $id): Response
    {
        return $this->redirectToRoute('clinic_portal_requests_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
