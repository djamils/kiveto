<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Close;

use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Presentation\Clinic\Controller\Consultation\Record\CockpitEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Closes the consultation from the cockpit's confirmation window, then comes
 * back to the cockpit, which now reads as the closed, read-only record.
 */
final class CloseConsultationController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/close', name: 'clinic_consultation_close', methods: ['POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        if (!$this->endpoint->isCsrfValid($request)) {
            $this->addFlash('error', 'Session expirée, rechargez la page.');

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
        }

        $summary = $request->request->getString('summary');

        try {
            $this->commandBus->dispatch(
                new CloseConsultation(
                    consultationId: $id,
                    closedByUserId: $user->id(),
                    summary: '' !== $summary ? $summary : null,
                )
            );

            $this->addFlash('success', 'Consultation clôturée — la facture part en sortie.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la clôture : ' . $e->getMessage());
        }

        return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
    }
}
