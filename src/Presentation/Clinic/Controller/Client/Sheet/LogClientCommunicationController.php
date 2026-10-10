<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Client\Application\Command\LogClientCommunication\LogClientCommunication;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients/{id}/communications', name: 'clinic_clients_communication_log', methods: ['POST'])]
final class LogClientCommunicationController extends AbstractClientSheetAction
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

        $saved = $this->attempt(fn () => $this->commandBus->dispatch(new LogClientCommunication(
            clinicId: $clinicId,
            clientId: $id,
            channel: $request->request->getString('channel', 'phone'),
            direction: $request->request->getString('direction', 'outbound'),
            subject: $request->request->getString('subject'),
            body: $this->trimmed($request, 'body'),
            authorName: $this->staffNameResolver->resolve($clinicId, $this->getUser()),
        )));

        if ($saved) {
            $this->addFlash('success', 'Échange consigné au journal.');
        } else {
            $this->addFlash('error', 'L’échange n’a pas pu être consigné : vérifiez l’objet et la longueur du contenu.');
        }

        return $this->backToSheet($id, 'communications');
    }
}
