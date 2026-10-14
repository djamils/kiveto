<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\Form\Animal\AnimalFormType;
use App\Presentation\Clinic\Form\Client\ClientFormType;
use App\Presentation\Clinic\ViewModel\Directory\DirectoryViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
            'createClientForm' => $this->createForm(ClientFormType::class),
            'createAnimalForm' => $this->createForm(AnimalFormType::class),
        ]);
    }
}
