<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\ViewModel\Directory\DirectoryAnimalInput;
use App\Presentation\Clinic\ViewModel\Directory\DirectoryViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Répertoire — the clinic's clients and their animals in one table, two views.
 *
 * Filtering, sorting and pagination are server-side and carried by the query
 * string, so a view is a shareable URL.
 */
#[Route('/clients', name: 'clinic_clients_list', methods: ['GET'])]
final class ListClientsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DirectoryViewBuilder $directory,
        private readonly ClockInterface $clock,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $clinic = $this->queryBus->ask(new GetClinic($clinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $view = $this->directory->page(
            $clinicId->toString(),
            $request,
            TimeZone::fromString($clinic->timeZone)->toNative(),
            $this->clock->now(),
        );

        $pagination = $view['pagination'];
        \assert(\is_array($pagination) && \is_int($pagination['pages']));

        // A filter narrowed the list under the page the visitor was on.
        if ($request->query->getInt('page', 1) > $pagination['pages']) {
            return $this->redirectToRoute('clinic_clients_list', ['page' => $pagination['pages']] + $request->query->all());
        }

        return $this->render('clinic/clients/list/index.html.twig', $view + [
            'create' => $this->createWindows($clinic),
        ]);
    }

    /**
     * What the two creation windows need: where to look clients, phones,
     * chips and addresses up, where to send, and the closed lists of the
     * animal form.
     *
     * @return array<string, mixed>
     */
    private function createWindows(ClinicDto $clinic): array
    {
        $today = $this->clock->now()->setTimezone(TimeZone::fromString($clinic->timeZone)->toNative());

        return [
            'urls' => [
                'clients'   => $this->generateUrl('clinic_directory_clients'),
                'phones'    => $this->generateUrl('clinic_directory_phone_owners'),
                'addresses' => $this->generateUrl('clinic_directory_addresses'),
                'communes'  => $this->generateUrl('clinic_directory_communes'),
                'chip'      => $this->generateUrl('clinic_admission_chip_lookup'),
                'client'    => $this->generateUrl('clinic_clients_create'),
                'animal'    => $this->generateUrl('clinic_animals_create'),
                'animalUrl' => $this->generateUrl('clinic_animals_view', ['id' => '__ID__']),
            ],
            'token'   => $this->csrfTokenManager->getToken('directory_create')->getValue(),
            'today'   => $today->format('Y-m-d'),
            'species' => array_map(
                static fn (string $key, array $species): array => ['v' => $key, 'l' => $species[1], 'e' => $species[2]],
                array_keys(DirectoryAnimalInput::SPECIES),
                DirectoryAnimalInput::SPECIES,
            ),
            'breeds' => DirectoryAnimalInput::BREEDS,
            'ages'   => array_map(
                static fn (string|int $key, array $age): array => ['v' => (string) $key, 'l' => $age[0]],
                array_keys(DirectoryAnimalInput::AGES),
                DirectoryAnimalInput::AGES,
            ),
            'maxAge' => DirectoryAnimalInput::MAX_AGE_YEARS,
        ];
    }
}
