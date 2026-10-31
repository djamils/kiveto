<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation;

use App\Context\ClientPortal\Application\Command\ShareConsultationReport\ReportShareResult;
use App\Context\ClientPortal\Application\Command\ShareConsultationReport\ShareConsultationReport;
use App\Context\ClientPortal\Domain\Exception\ReportNotShareableException;
use App\Presentation\Clinic\Controller\Consultation\Record\CockpitEndpoint;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitContextBuilder;
use App\Presentation\Clinic\ViewModel\Consultation\PortalShareViewBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Partager sur le Portal » from the documents panel of a closed
 * consultation (S2f): publishes the report to the animal's primary owner,
 * or shares it again. Never blocked by an owner without Portal account: the
 * report then waits for their link, which the toast says.
 */
final class ShareConsultationReportOnPortalController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CockpitEndpoint $endpoint,
        private readonly CockpitContextBuilder $contextBuilder,
        private readonly PortalShareViewBuilder $portalShare,
    ) {
    }

    #[Route('/clinic/consultations/{id}/documents/portal-share', name: 'clinic_consultation_portal_share', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        if (!$this->endpoint->isCsrfValid($request)) {
            return new JsonResponse(['success' => false, 'error' => 'Session expirée, rechargez la page.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);
        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        try {
            $shared = $this->commandBus->dispatch(new ShareConsultationReport(
                clinicId: $clinicId->toString(),
                consultationId: $id,
                actorUserId: $user->id(),
            ));
        } catch (ReportNotShareableException $exception) {
            return ReportNotShareableException::UNKNOWN === $exception->reason
                ? self::notFound()
                : new JsonResponse(['success' => false, 'error' => PortalShareViewBuilder::REASONS[$exception->reason] ?? $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\LogicException) { // malformed id (InvalidArgumentException)
            return self::notFound();
        }

        \assert($shared instanceof ReportShareResult);
        $timeZone = $this->contextBuilder->letterhead($clinicId->toString())['timeZone'];

        return new JsonResponse([
            'success'     => true,
            'message'     => $shared->linked ? 'Compte rendu partagé sur le Portal.' : 'Partagé — visible dès que le client activera son espace Portal',
            'portalShare' => $this->portalShare->build($clinicId->toString(), $id, $timeZone),
        ]);
    }

    private static function notFound(): JsonResponse
    {
        return new JsonResponse(['success' => false, 'error' => 'Consultation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
    }
}
