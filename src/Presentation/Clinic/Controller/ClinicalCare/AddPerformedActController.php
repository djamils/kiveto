<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\ClinicalCare\Application\Command\AddPerformedAct\AddPerformedAct;
use App\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AddPerformedActController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/clinic/consultations/{id}/acts', name: 'clinic_consultation_add_act', methods: ['POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();
        \assert(null !== $user);

        $label       = $request->request->get('label');
        $quantity    = $request->request->get('quantity', 1.0);
        $performedAt = $request->request->get('performedAt', (new \DateTimeImmutable())->format('c'));

        if (empty($label)) {
            $this->addFlash('error', 'Le libellé de l\'acte est obligatoire.');

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
        }

        try {
            $this->commandBus->dispatch(
                new AddPerformedAct(
                    consultationId: $id,
                    label: $label,
                    quantity: (float) $quantity,
                    performedAt: $performedAt,
                    createdByUserId: $user->id(),
                )
            );

            $this->addFlash('success', 'Acte ajouté.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
    }
}
