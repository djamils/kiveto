<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\ClinicalCare\Application\Command\AddClinicalNote\AddClinicalNote;
use App\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AddClinicalNoteController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/clinic/consultations/{id}/notes', name: 'clinic_consultation_add_note', methods: ['POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();
        \assert(null !== $user);

        $noteType = $request->request->get('noteType');
        $content  = $request->request->get('content');

        if (empty($noteType) || empty($content)) {
            $this->addFlash('error', 'Le type et le contenu de la note sont obligatoires.');

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
        }

        try {
            $this->commandBus->dispatch(
                new AddClinicalNote(
                    consultationId: $id,
                    noteType: $noteType,
                    content: $content,
                    createdByUserId: $user->id(),
                )
            );

            $this->addFlash('success', 'Note clinique ajoutée.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
    }
}
