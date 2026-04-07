<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\ClinicalCare\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class RecordChiefComplaintController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/clinic/consultations/{id}/chief-complaint', name: 'clinic_consultation_record_chief_complaint', methods: ['POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        $chiefComplaint = $request->request->get('chiefComplaint');

        if (empty($chiefComplaint)) {
            $this->addFlash('error', 'Le motif de consultation est obligatoire.');

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
        }

        try {
            $this->commandBus->dispatch(
                new RecordChiefComplaint(
                    consultationId: $id,
                    chiefComplaint: $chiefComplaint,
                )
            );

            $this->addFlash('success', 'Motif de consultation enregistré.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
    }
}
