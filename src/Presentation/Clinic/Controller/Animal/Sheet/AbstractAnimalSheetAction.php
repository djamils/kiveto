<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Forms of the animal sheet load the animal from the current clinic first,
 * then come back to the tab they were submitted from.
 */
abstract class AbstractAnimalSheetAction extends AbstractSheetAction
{
    protected const string CSRF_ID = 'animal_sheet';

    public static function getSubscribedServices(): array
    {
        return [...parent::getSubscribedServices(), QueryBusInterface::class];
    }

    /**
     * The animal of the current clinic, or a 404: an id from another clinic
     * is answered exactly like an id that does not exist.
     */
    protected function animal(string $animalId): AnimalView
    {
        $queryBus = $this->container->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);

        try {
            $animal = $queryBus->ask(new GetAnimalById($this->clinicId(), $animalId));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Animal introuvable.');
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        if (!$animal instanceof AnimalView) {
            throw $this->createNotFoundException('Animal introuvable.');
        }

        return $animal;
    }

    /**
     * A deceased or archived animal keeps its file readable but closed.
     */
    protected function isOpen(AnimalView $animal): bool
    {
        return 'active' === $animal->status && 'alive' === $animal->lifeCycle->lifeStatus;
    }

    protected function refuseClosedFile(string $animalId, string $tab = 'synthese'): RedirectResponse
    {
        $this->addFlash('error', 'Ce dossier est clos : l’animal est décédé ou sa fiche est archivée.');

        return $this->backToSheet($animalId, $tab);
    }

    protected function backToSheet(string $animalId, string $tab = 'synthese'): RedirectResponse
    {
        return $this->redirectToRoute(
            'clinic_animals_view',
            'synthese' === $tab ? ['id' => $animalId] : ['id' => $animalId, 'tab' => $tab],
            RedirectResponse::HTTP_SEE_OTHER,
        );
    }
}
