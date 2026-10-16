<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Client\Story;

use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\CreateClient\NewAnimalDto;
use App\Context\Client\Application\Command\UpdateClientPostalAddress\PostalAddressDto;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Animal\Factory\PreventiveCareRecordEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use Zenstruck\Foundry\Story;

/**
 * The Paris directory (Répertoire) at a realistic size: enough clients and
 * animals for several pages at 10 per page, cities for the « Ville » filter,
 * every species for « Espèce », archived clients for « Statut client », a
 * deceased and an archived animal, and reminders due soon or late for « État ».
 *
 * Built after ClientDataStory and AnimalDataStory, which keep the hand-made
 * edge cases (no contact, many animals…).
 *
 * The Girard household shares a landline: Margaux Girard was opened from the
 * « Nouveau client » window once its « Autre personne » was confirmed, with
 * her civility, address, SMS reminders, the clinic's news and two animals
 * (one of age unknown, one carrying a foreign chip). Typing that number, or a
 * chip of the list below, in the window shows its warnings.
 */
final class DirectoryCrowdStory extends Story
{
    /** The Girard household's landline, on Thomas's record and on Margaux's. */
    public const string SHARED_LANDLINE = '+33145670001';
    /**
     * [first name, last name, city, phone, archived, months as a client,
     *  animals: list of [name, species, breed, sex, age in months, chip, state]].
     *
     * State: null (alive, active), 'deceased', 'archived'.
     */
    private const array CROWD = [
        ['Thomas', 'Girard', 'Paris', '+33612345601', false, 67, [['Pacha', 'dog', 'Labrador retriever', 'male', 72, true, null]]],
        ['Ana', 'Torres', 'Montreuil', '+33698765402', false, 81, [['Simba', 'cat', 'Maine coon', 'male', 48, true, null], ['Nube', 'cat', 'Européen', 'female', 30, true, null]]],
        ['Julie', 'Ferrand', 'Paris', '+33711223303', false, 25, [['Mochi', 'cat', 'Européen', 'female', 24, true, null]]],
        ['Karim', 'Bensaïd', 'Vincennes', '+33655443304', false, 40, [['Nala', 'dog', 'Berger australien', 'female', 36, true, null]]],
        ['Linh', 'Pham', 'Paris', '+33766554405', false, 8, [['Caramel', 'nac', 'Lapin bélier', 'male', 24, false, null]]],
        ['Paolo', 'Costa', 'Boulogne-Billancourt', '+33622334406', false, 84, [['Rio', 'dog', 'Cavalier King Charles', 'male', 96, true, null]]],
        ['David', 'Weiss', 'Paris', '+33677889907', false, 53, [['Plume', 'cat', 'Chartreux', 'female', 144, true, null]]],
        ['Sophie', 'Rey', 'Montreuil', '+33712211208', false, 102, [['Perle', 'cat', 'Sacré de Birmanie', 'female', 4, true, null], ['Falco', 'dog', 'Malinois', 'male', 60, true, null]]],
        ['Yanis', 'Silva', 'Paris', '+33690807009', false, 11, [['Balto', 'dog', 'Husky', 'male', 96, true, null]]],
        ['Emma', 'Klein', 'Vincennes', '+33745544510', false, 26, [['Nori', 'cat', 'Bengal', 'female', 36, true, null], ['Choco', 'nac', 'Lapin nain', 'male', 12, false, null]]],
        ['Pierre', 'Lemaire', 'Paris', '+33644112211', true, 87, []],
        ['Hugo', 'Marchand', 'Saint-Mandé', '+33611002212', false, 15, [['Pixel', 'cat', 'Sphynx', 'male', 20, true, null]]],
        ['Camille', 'Roche', 'Paris', '+33622003313', false, 33, [['Kiwi', 'other', 'Perruche ondulée', 'female', 18, false, null], ['Mango', 'other', 'Perruche ondulée', 'male', 18, false, null], ['Coco', 'other', 'Inséparable', 'unknown', 30, false, null], ['Zazou', 'nac', 'Furet', 'male', 26, true, null]]],
        ['Inès', 'Benali', 'Montreuil', '+33633004414', false, 5, [['Sushi', 'cat', 'Européen', 'female', 3, false, null]]],
        ['Lucas', 'Garnier', 'Paris', '+33644005515', false, 46, [['Ulysse', 'dog', 'Golden retriever', 'male', 130, true, 'deceased'], ['Tao', 'dog', 'Shiba inu', 'male', 28, true, null]]],
        ['Chloé', 'Faure', 'Boulogne-Billancourt', '+33655006616', false, 19, [['Gaïa', 'dog', 'Border collie', 'female', 40, true, null]]],
        ['Antoine', 'Perrot', 'Paris', '+33666007717', true, 70, [['Vasco', 'cat', 'Européen', 'male', 110, true, 'archived']]],
        ['Léa', 'Mercier', 'Vincennes', '+33677008818', false, 12, [['Praline', 'nac', 'Cochon d\'Inde', 'female', 14, false, null], ['Noisette', 'nac', 'Cochon d\'Inde', 'female', 14, false, null]]],
        ['Mehdi', 'Haddad', 'Paris', '+33688009919', false, 29, [['Rocky', 'dog', 'Staffordshire', 'male', 50, true, null]]],
        ['Clara', 'Fontaine', 'Saint-Mandé', '+33699001120', false, 58, [['Milo', 'cat', 'Ragdoll', 'male', 62, true, null], ['Lila', 'cat', 'Ragdoll', 'female', 62, true, null], ['Oréo', 'cat', 'Européen', 'male', 20, true, null]]],
        ['Nathan', 'Chevallier', 'Paris', '+33610102021', false, 3, []],
        ['Manon', 'Lopez', 'Montreuil', '+33620203022', false, 37, [['Biscotte', 'dog', 'Bichon frisé', 'female', 84, true, null]]],
        ['Romain', 'Gauthier', 'Paris', '+33630304023', false, 64, [['Hermès', 'cat', 'Abyssin', 'male', 70, true, null]]],
        ['Sarah', 'Blanc', 'Vincennes', '+33640405024', false, 22, [['Tornade', 'dog', 'Jack Russell', 'female', 30, true, null]]],
        ['Julien', 'Moulin', 'Boulogne-Billancourt', '+33650506025', true, 95, [['Gribouille', 'cat', 'Européen', 'male', 160, false, 'deceased']]],
        ['Alice', 'Robin', 'Paris', '+33660607026', false, 9, [['Cookie', 'dog', 'Cocker anglais', 'female', 10, true, null]]],
    ];

    /**
     * Preventive acts dated from today: [owner index, animal index, label,
     * months ago administered, days until the next dose (negative = late)].
     */
    private const array REMINDERS = [
        [5, 0, 'Rage', 12, 23],
        [4, 0, 'Myxomatose', 6, 12],
        [9, 0, 'CHPPiL', 12, 5],
        [7, 0, '2e injection primo', 1, 9],
        [15, 0, 'CHPPiL', 13, -14],
        [19, 2, 'Typhus-Coryza', 11, 18],
        [0, 0, 'CHPPiL', 4, 240],
    ];

    /**
     * Medical alerts: [owner index, animal index, kind, label].
     */
    private const array ALERTS = [
        [0, 0, 'ALLERGY', 'Pénicilline'],
        [0, 0, 'CHRONIC_CONDITION', 'Maladie parodontale st. 2'],
        [5, 0, 'CHRONIC_CONDITION', 'Suivi cardiaque MVD st. B2'],
        [6, 0, 'CHRONIC_CONDITION', 'IRC stade 2 — perfusions'],
        [9, 0, 'ALLERGY', 'Sédation difficile'],
        [9, 0, 'CHRONIC_CONDITION', 'Dermatite'],
        [7, 1, 'CHRONIC_CONDITION', 'Otites récidivantes'],
    ];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function build(): void
    {
        $clinicId  = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $today     = new \DateTimeImmutable('today 10:00');
        $animalIds = [];

        foreach (self::CROWD as $i => [$first, $last, $city, $phone, $archived, $months, $animals]) {
            $clientId = \sprintf('01936e19-d1d1-7000-8000-%012x', $i + 1);

            $factory = ClientEntityFactory::new()
                ->withId($clientId)
                ->withClinicId($clinicId)
                ->withName($first, $last)
                ->withPostalAddress(\sprintf('%d rue de la République', 3 + $i * 7), $city, 'FR')
                ->with(['createdAt' => $today->modify(\sprintf('-%d months', $months))])
            ;

            ($archived ? $factory->archived() : $factory->active())->create();

            ContactMethodEntityFactory::new()->forClient($clientId)->mobile()->phone($phone)->primary()->create();
            if (0 === $i) {
                ContactMethodEntityFactory::new()->forClient($clientId)->home()->phone(self::SHARED_LANDLINE)->create();
            }
            ContactMethodEntityFactory::new()
                ->forClient($clientId)
                ->email($this->email($first, $last))
                ->home()
                ->create()
            ;

            foreach ($animals as $j => [$name, $species, $breed, $sex, $ageMonths, $chipped, $state]) {
                $animalId = \sprintf('01936e19-d2d2-7000-8000-%06x%06x', $i + 1, $j + 1);

                $animal = AnimalEntityFactory::new()
                    ->withId($animalId)
                    ->withClinicId($clinicId)
                    ->withName($name)
                    ->withBreed($breed, Species::from($species))
                    ->neutered()
                    ->with([
                        'sex'             => Sex::from($sex),
                        'birthDate'       => $today->modify(\sprintf('-%d months', $ageMonths))->setTime(0, 0),
                        'microchipNumber' => $chipped ? \sprintf('25026%010d', 4_100_000_000 + $i * 1_000 + $j) : null,
                        'isMixedBreed'    => false,
                    ])
                ;

                $animal = 'archived' === $state ? $animal->archived() : $animal->active();
                $animal = 'deceased' === $state
                    ? $animal->deceased()
                    : $animal->alive();

                $animal->create();
                $animalIds[$i][$j] = $animalId;

                OwnershipEntityFactory::new()
                    ->withAnimalId($animalId)
                    ->withClientId($clientId)
                    ->primary()
                    ->active()
                    ->create()
                ;
            }
        }

        $this->openHouseholdMember($clinicId, $today);

        foreach (self::ALERTS as [$owner, $index, $kind, $label]) {
            $this->commandBus->dispatch(new AddMedicalAlert(
                clinicId: $clinicId,
                animalId: $animalIds[$owner][$index],
                kind: $kind,
                label: $label,
                note: null,
            ));
        }

        foreach (self::REMINDERS as [$owner, $index, $label, $monthsAgo, $dueInDays]) {
            PreventiveCareRecordEntityFactory::new()
                ->forAnimal($clinicId, $animalIds[$owner][$index])
                ->act(
                    PreventiveCareKind::VACCINATION,
                    $label,
                    $today->modify(\sprintf('-%d months', $monthsAgo)),
                    $today->modify(\sprintf('%+d days', $dueInDays)),
                )
                ->create()
            ;
        }
    }

    /**
     * Through the command the « Nouveau client » window sends: the record,
     * its preferences and its animals at once.
     */
    private function openHouseholdMember(string $clinicId, \DateTimeImmutable $today): void
    {
        $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: 'Margaux',
            lastName: 'Girard',
            contactMethods: [
                new ContactMethodDto('phone', 'mobile', '+33612345690', true),
                new ContactMethodDto('phone', 'home', self::SHARED_LANDLINE, false),
                new ContactMethodDto('email', 'home', 'margaux.girard@exemple.fr', true),
            ],
            civility: 'madam',
            postalAddress: new PostalAddressDto('3 Rue de la République', 'Paris', 'FR', 'Bât. A, 2e étage', '75011'),
            preferredChannel: 'sms',
            channelsByConsentKind: [
                'appointment_reminders' => ['sms'],
                'vaccination_reminders' => ['sms'],
                'marketing'             => ['sms', 'email'],
            ],
            animals: [
                // « Date inconnue · Environ 3 ans »: an approximate birth date.
                new NewAnimalDto('Figue', 'cat', 'female', 'neutered', false, 'Européen', $today->modify('-3 years')->format('Y-m-01'), null),
                // Adopted abroad: a German chip, outside the I-CAD file.
                new NewAnimalDto('Pistou', 'dog', 'male', 'intact', true, 'Croisé', $today->modify('-5 years')->format('Y-m-d'), '276098106543210'),
            ],
        ));
    }

    private function email(string $first, string $last): string
    {
        $ascii = static fn (string $s): string => strtolower((string) preg_replace('/[^a-z]/i', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $s)));

        return $ascii($first) . '.' . $ascii($last) . '@exemple.fr';
    }
}
