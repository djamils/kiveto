<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Sends a patient one step back on the flow board: « Renvoyer en salle
 * d'attente » from a room, « Renvoyer en prise en charge » from the desk.
 */
#[Route('/admission/{admissionId}/move', name: 'clinic_admission_move', methods: ['POST'])]
final class MoveAdmissionController extends AbstractController
{
    private const array TARGETS = [
        'in_waiting_room'      => 'Salle d\'attente',
        'in_consultation_room' => 'Pris en charge',
    ];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
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

        $target = $request->request->getString('to');

        if (!isset(self::TARGETS[$target])) {
            $this->addFlash('error', 'Destination inconnue.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new UpdateAdmissionLocationStatus(
                clinicId: $currentClinicId->toString(),
                admissionId: $admissionId,
                newLocationStatus: $target,
            ));

            $name = trim($request->request->getString('name'));
            $this->addFlash('success', ('' !== $name ? $name : 'Patient') . ' → ' . self::TARGETS[$target]);
        } catch (AdmissionNotFoundException) {
            $this->addFlash('error', 'Admission introuvable.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
