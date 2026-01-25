<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal;

use App\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/animals/{id}/edit', name: 'clinic_animals_edit', methods: ['GET'])]
final class EditAnimalController extends AbstractController
{
    private const string CSRF_ID = 'clinic_animal_form';

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

        $animal = $this->queryBus->ask(new GetAnimalById(
            clinicId: $currentClinicId->toString(),
            animalId: $id,
        ));

        if (null === $animal) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        return $this->render('clinic/animals/form_layout15.html.twig', [
            'animal'            => $animal,
            'csrf_token'        => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }
}
