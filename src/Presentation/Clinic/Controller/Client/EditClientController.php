<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client;

use App\Client\Application\Query\GetClientById\GetClientById;
use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/clients/{id}/edit', name: 'clinic_clients_edit', methods: ['GET'])]
final class EditClientController extends AbstractController
{
    private const string CSRF_ID = 'clinic_client_form';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $client = $this->queryBus->ask(new GetClientById(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
        ));

        if (null === $client) {
            throw $this->createNotFoundException('Client introuvable.');
        }

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        return $this->render('clinic/clients/form_layout15.html.twig', [
            'client'            => $client,
            'csrf_token'        => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }
}
