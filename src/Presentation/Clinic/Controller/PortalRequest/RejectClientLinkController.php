<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Command\RejectClientLink\RejectClientLink;
use App\Context\ClientPortal\Application\Query\GetClinicLinkRequest\GetClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * « Refuser » a « Rattachement »: the account's pending links to the files
 * shown are revoked (`link_rejected`) and the clinic is no longer suggested
 * to it; the client's consents are not touched. A colleague who answered
 * first wins (« Demande déjà traitée. »). Needs `manage_animals`.
 */
#[Route('/portal-requests/rattachements/{userId}/reject', name: 'clinic_portal_link_requests_reject', requirements: ['userId' => Requirement::UUID], methods: ['POST'])]
final class RejectClientLinkController extends AbstractClientLinkAnswer
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly ClinicPermissionGuard $permissions,
    ) {
    }

    public function __invoke(string $userId, Request $request): Response
    {
        $this->guard($request);
        $this->permissions->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId = $this->clinicId();
        $rejected = $this->commandBus->dispatch(new RejectClientLink($clinicId, $userId, $this->clientIds($request)));
        \assert(\is_int($rejected));

        $this->addFlash(0 === $rejected ? 'error' : 'success', 0 === $rejected
            ? 'Demande déjà traitée.'
            : 'Rattachement refusé — la clinique ne sera plus proposée à ce compte Portal.');

        return $this->back($userId, $this->queryBus->ask(new GetClinicLinkRequest($clinicId, $userId)) instanceof ClinicLinkRequest);
    }
}
