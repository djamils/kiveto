<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Animal\Application\Query\FindByMicrochip\AnimalMicrochipView;
use App\Context\Animal\Application\Query\FindByMicrochip\FindByMicrochip;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Presentation\Clinic\ViewModel\Dashboard\PatientFactsReader;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Lire la puce » on the flow board: the chip read (or typed) at the desk
 * finds the clinic's file of the animal, with its owner and its alerts, so an
 * emergency is admitted — or an unknown patient identified — on the spot.
 */
#[Route('/admission/chip', name: 'clinic_admission_chip_lookup', methods: ['GET'])]
final class LookupMicrochipController extends AbstractController
{
    private const array SPECIES = [
        'dog' => ['🐕', 'Chien'],
        'cat' => ['🐈', 'Chat'],
        'nac' => ['🐇', 'NAC'],
    ];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);
        $clinicId = $currentClinicId->toString();

        $chip = preg_replace('/\D/', '', $request->query->getString('chip')) ?? '';

        if (15 !== \strlen($chip)) {
            return new JsonResponse(['found' => false, 'error' => 'Un numéro de puce compte 15 chiffres.'], 422);
        }

        $match = $this->queryBus->ask(new FindByMicrochip($clinicId, $chip));

        if (!$match instanceof AnimalMicrochipView) {
            return new JsonResponse(['found' => false, 'chip' => $chip]);
        }

        $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $match->animalId));
        \assert($animal instanceof AnimalView);

        $ownerId = $animal->primaryOwnerClientId();
        $owner   = null === $ownerId ? null : $this->queryBus->ask(new GetClientById($clinicId, $ownerId));
        $owner   = $owner instanceof ClientView ? $owner : null;

        $alerts = $this->queryBus->ask(new ListMedicalAlertsForAnimal($clinicId, $animal->id));
        \assert(\is_array($alerts));

        [$emoji, $species] = self::SPECIES[$animal->species] ?? ['🐾', 'Animal'];
        $breed             = $animal->breedName ?? $species;
        $age               = null === $animal->birthDate ? null : PatientFactsReader::age($animal, new \DateTimeImmutable());

        return new JsonResponse([
            'found'  => true,
            'chip'   => $chip,
            'animal' => [
                'id'   => $animal->id,
                'nom'  => $animal->name,
                'esp'  => $emoji,
                'race' => null === $age ? $breed : $breed . ' · ' . $age,
                'cli'  => null === $owner ? 'Sans propriétaire' : $owner->fullName(),
                'tel'  => null === $owner ? null : PatientFactsReader::phone($owner),
                'vig'  => array_values(array_map(
                    static fn (MedicalAlertView $alert): string => '⚠ ' . $alert->label,
                    array_filter($alerts, static fn (mixed $alert): bool => $alert instanceof MedicalAlertView),
                )),
                'puce' => implode(' ', str_split($chip, 3)),
            ],
        ]);
    }
}
