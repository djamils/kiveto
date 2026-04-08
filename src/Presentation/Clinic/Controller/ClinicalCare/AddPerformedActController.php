<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\Context\ClinicalCare\Application\Command\AddPerformedAct\AddPerformedAct;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
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
        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $label       = $request->request->getString('label');
        $quantity    = (float) $request->request->getString('quantity', '1');
        $performedAt = $request->request->getString('performedAt', (new \DateTimeImmutable())->format('c'));

        if ('' === $label) {
            $this->addFlash('error', 'Le libellé de l\'acte est obligatoire.');

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
        }

        try {
            $this->commandBus->dispatch(
                new AddPerformedAct(
                    consultationId: $id,
                    label: $label,
                    quantity: $quantity,
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
