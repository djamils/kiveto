<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\AddClientNote\AddClientNote;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients/{id}/notes', name: 'clinic_clients_note_add', methods: ['POST'])]
final class AddClientNoteController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly StaffNameResolver $staffNameResolver,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $clinicId = $this->clinicId();

        $saved = $this->attempt(fn () => $this->commandBus->dispatch(new AddClientNote(
            clinicId: $clinicId,
            clientId: $id,
            kind: $request->request->getString('kind', 'information'),
            content: $request->request->getString('content'),
            authorName: $this->staffNameResolver->resolve($clinicId, $this->getUser()),
        )));

        if (!$saved) {
            $this->addFlash('error', 'La note n’a pas pu être enregistrée : vérifiez qu’elle n’est ni vide ni trop longue.');

            return $this->backToSheet($id);
        }

        $this->addFlash('success', 'Note interne · note mise à jour');

        return $this->backToSheet($id, 'cNote');
    }
}
