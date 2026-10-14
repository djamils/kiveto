<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Profile;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\ViewModel\Animal\AnimalSheetBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/{id}', name: 'clinic_animals_view', methods: ['GET'])]
final class ViewAnimalController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly AnimalSheetBuilder $sheetBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $animal = $this->queryBus->ask(new GetAnimalById(
                clinicId: $currentClinicId->toString(),
                animalId: $id,
            ));
        } catch (\DomainException) {
            // Unknown here: the query refuses another clinic's animal the same way.
            throw $this->createNotFoundException('Animal introuvable.');
        } catch (\InvalidArgumentException) {
            // Unknown here: the query refuses another clinic's animal the same way.
            throw $this->createNotFoundException('Animal introuvable.');
        }

        if (!$animal instanceof AnimalView) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $clinicTz = TimeZone::fromString($clinic->timeZone)->toNative();
        $now      = $this->clock->now();

        return $this->render('clinic/animals/view/index.html.twig', [
            'animal'            => $animal,
            'sheet'             => $this->sheetBuilder->build($animal, $clinicTz, $now),
            'activeTab'         => $request->query->getString('tab', 'synthese'),
            'today'             => $now->setTimezone($clinicTz)->format('Y-m-d'),
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }
}
