<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Command\ConfirmClientLink\ConfirmClientLink;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * « Confirmer » a « Rattachement »: the account's pending links to the files
 * shown become confirmed and it reaches them from the Portal. A colleague
 * who answered first wins (« Demande déjà traitée. »). Needs
 * `manage_animals`.
 */
#[Route('/portal-requests/rattachements/{userId}/confirm', name: 'clinic_portal_link_requests_confirm', requirements: ['userId' => Requirement::UUID], methods: ['POST'])]
final class ConfirmClientLinkController extends AbstractClientLinkAnswer
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly ClinicPermissionGuard $permissions,
        private readonly StaffNameResolver $staffNames,
    ) {
    }

    public function __invoke(string $userId, Request $request): Response
    {
        $this->guard($request);
        $this->permissions->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId              = $this->clinicId();
        [$actorId, $actorName] = $this->actor($this->staffNames, $clinicId);
        $confirmed             = $this->commandBus->dispatch(new ConfirmClientLink($clinicId, $userId, $this->clientIds($request), $actorId, $actorName));
        \assert(\is_int($confirmed));

        return $this->back($request, $this->queryBus, $clinicId, $userId, $confirmed, 'Rattachement confirmé — le compte Portal accède désormais au dossier.');
    }
}
