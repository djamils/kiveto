<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\ClinicalCare\Application\Command\CloseConsultation\CloseConsultation;
use App\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CloseConsultationController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/clinic/consultations/{id}/close', name: 'clinic_consultation_close', methods: ['POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();
        \assert(null !== $user);

        $summary = $request->request->get('summary');

        try {
            $this->commandBus->dispatch(
                new CloseConsultation(
                    consultationId: $id,
                    closedByUserId: $user->id(),
                    summary: !empty($summary) ? $summary : null,
                )
            );

            $this->addFlash('success', 'Consultation clôturée avec succès.');

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la clôture : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
        }
    }
}
