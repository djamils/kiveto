<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Passer en sortie »: the patient is done and goes to the desk to be
 * charged. When a consultation is still open it is closed — closing it is
 * what sends the patient to the desk and completes the booking; a patient
 * taken in charge without one (e.g. back from the theatre) is simply moved.
 */
#[Route(
    '/admission/{admissionId}/send-to-checkout',
    name: 'clinic_admission_send_to_checkout',
    methods: ['POST'],
)]
final class SendAdmissionToCheckoutController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $admissionId): Response
    {
        $token = $request->request->getString('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('flux_action', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);
        $clinicId = $currentClinicId->toString();

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $consultationId = $request->request->getString('consultationId');
        $name           = trim($request->request->getString('name'));

        try {
            if ('' !== $consultationId) {
                // Same clinic and same visit, or nothing is closed.
                $details = $this->queryBus->ask(new GetConsultationDetails($consultationId, $clinicId));

                if (!$details instanceof ConsultationDetailsDTO || $details->admissionId !== $admissionId) {
                    throw new \DomainException('Consultation introuvable pour ce patient.');
                }

                if (!$details->isClosed()) {
                    $this->commandBus->dispatch(new CloseConsultation(
                        consultationId: $consultationId,
                        closedByUserId: $user->id(),
                        summary: null,
                    ));
                }
            }

            // Already done by the closing above when it was the last open
            // consultation of the visit; otherwise the patient is moved here.
            $this->commandBus->dispatch(new UpdateAdmissionLocationStatus(
                clinicId: $clinicId,
                admissionId: $admissionId,
                newLocationStatus: 'at_reception',
            ));

            $this->addFlash('success', ('' !== $name ? $name : 'Patient') . ' → Sortie');
        } catch (AdmissionNotFoundException) {
            $this->addFlash('error', 'Admission introuvable.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException) {
            // The read side throws when the consultation is not this clinic's.
            $this->addFlash('error', 'Consultation introuvable pour ce patient.');
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
