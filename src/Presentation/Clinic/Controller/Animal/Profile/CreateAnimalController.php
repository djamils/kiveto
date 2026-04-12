<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Profile;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Presentation\Clinic\Form\Animal\AnimalFormType;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Infrastructure\Http\ReturnToResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/animals/create', name: 'clinic_animals_create', methods: ['POST'])]
final class CreateAnimalController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ReturnToResolver $returnToResolver,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $form = $this->createForm(AnimalFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(\is_array($data));

            $rawOwnerId           = $data['primaryOwnerClientId'] ?? null;
            $primaryOwnerClientId = \is_string($rawOwnerId) ? $rawOwnerId : '';

            $owner = $this->queryBus->ask(new GetClientById(
                clinicId: $currentClinicId->toString(),
                clientId: $primaryOwnerClientId,
            ));

            if (!$owner instanceof ClientView) {
                $form->get('primaryOwnerClientId')->addError(
                    new FormError('Ce propriétaire est introuvable dans cette clinique.'),
                );
            } else {
                $name      = \is_string($data['name'] ?? null) ? $data['name'] : '';
                $species   = \is_string($data['species'] ?? null) ? $data['species'] : '';
                $sex       = \is_string($data['sex'] ?? null) ? $data['sex'] : 'unknown';
                $breedName = \is_string($data['breedName'] ?? null) && '' !== $data['breedName']
                    ? $data['breedName']
                    : null;
                $birthDateRaw = $data['birthDate'] ?? null;
                $birthDate    = $birthDateRaw instanceof \DateTimeInterface
                    ? $birthDateRaw->format('Y-m-d')
                    : (\is_string($birthDateRaw) && '' !== $birthDateRaw ? $birthDateRaw : null);

                $this->commandBus->dispatch(new CreateAnimal(
                    clinicId: $currentClinicId->toString(),
                    name: $name,
                    species: $species,
                    sex: $sex,
                    reproductiveStatus: 'unknown',
                    isMixedBreed: false,
                    breedName: $breedName,
                    birthDate: $birthDate,
                    color: null,
                    photoUrl: null,
                    microchipNumber: null,
                    tattooNumber: null,
                    passportNumber: null,
                    registryType: 'none',
                    registryNumber: null,
                    sireNumber: null,
                    lifeStatus: 'alive',
                    deceasedAt: null,
                    missingSince: null,
                    transferStatus: 'none',
                    soldAt: null,
                    givenAt: null,
                    auxiliaryContactFirstName: null,
                    auxiliaryContactLastName: null,
                    auxiliaryContactPhoneNumber: null,
                    primaryOwnerClientId: $primaryOwnerClientId,
                    secondaryOwnerClientIds: [],
                ));

                $rawReturnToData = $form->has('_returnTo') ? $form->get('_returnTo')->getData() : null;
                $rawReturnTo     = \is_string($rawReturnToData) ? $rawReturnToData : '';
                $targetUrl       = $this->returnToResolver->resolve(
                    '' !== $rawReturnTo ? $rawReturnTo : null,
                    'clinic_clients_list',
                    ['tab' => 'animals'],
                );

                $this->addFlash('success', \sprintf('Animal "%s" créé avec succès.', $name));

                if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                    $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

                    return $this->render(
                        'clinic/clients/list/_create_animal_success.stream.html.twig',
                        ['targetUrl' => $targetUrl],
                        new Response('', Response::HTTP_OK, [
                            'Content-Type'   => TurboBundle::STREAM_MEDIA_TYPE,
                            'Turbo-Location' => $targetUrl,
                        ]),
                    );
                }

                return $this->redirect($targetUrl, Response::HTTP_SEE_OTHER);
            }
        }

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render(
                'clinic/clients/list/_create_animal_error.stream.html.twig',
                ['form' => $form],
                new Response('', Response::HTTP_UNPROCESSABLE_ENTITY, [
                    'Content-Type' => TurboBundle::STREAM_MEDIA_TYPE,
                ]),
            );
        }

        return $this->render(
            'clinic/clients/list/_form_animal_body.html.twig',
            ['form' => $form],
        );
    }
}
