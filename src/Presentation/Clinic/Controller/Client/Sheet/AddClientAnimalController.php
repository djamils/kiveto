<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients/{id}/animals', name: 'clinic_clients_animal_add', methods: ['POST'])]
final class AddClientAnimalController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $clinicId = $this->clinicId();

        $client = $this->queryBus->ask(new GetClientById($clinicId, $id));

        if (!$client instanceof ClientView) {
            throw $this->createNotFoundException('Client introuvable.');
        }

        if ('archived' === $client->status) {
            $this->addFlash('error', 'Cette fiche est archivée : désarchivez-la avant d’y ajouter un animal.');

            return $this->backToSheet($id);
        }

        $name = $this->trimmed($request, 'name');

        if (null === $name) {
            $this->addFlash('error', 'Le nom de l’animal est obligatoire.');

            return $this->backToSheet($id);
        }

        $created = $this->attempt(fn () => $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $name,
            species: $request->request->getString('species', 'dog'),
            sex: $request->request->getString('sex', 'unknown'),
            reproductiveStatus: $request->request->getBoolean('neutered') ? 'neutered' : 'intact',
            isMixedBreed: false,
            breedName: $this->trimmed($request, 'breed'),
            birthDate: $this->trimmed($request, 'birth_date'),
            color: null,
            photoUrl: null,
            microchipNumber: $this->trimmed($request, 'microchip'),
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $id,
            secondaryOwnerClientIds: [],
        )));

        if ($created) {
            $this->addFlash('success', \sprintf('%s a été ajouté au dossier.', $name));
        } else {
            $this->addFlash('error', 'L’animal n’a pas pu être créé : vérifiez le nom, la date de naissance et le transpondeur.');
        }

        return $this->backToSheet($id);
    }
}
