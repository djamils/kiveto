<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Animal\Story;

use App\Context\Animal\Application\Command\ArchiveAnimal\ArchiveAnimal;
use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Animal\Application\Command\RecordAnimalWeight\RecordAnimalWeight;
use App\Context\Animal\Application\Command\RecordPreventiveCare\RecordPreventiveCare;
use App\Context\Animal\Application\Command\UpdateAnimalLifeCycle\UpdateAnimalLifeCycle;
use App\Context\Animal\Application\Command\UpdateAnimalTransfer\UpdateAnimalTransfer;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\ClientPortal\Story\ClientPortalStatesStory;
use App\Fixtures\Context\ClientPortal\Story\PortalDocumentsStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The animals of the Portal « Mes animaux » (slice 3), on the accounts of
 * slice 2 (Paris clinic). States covered here:
 *
 * | Account (phone)                    | Animal       | State                                                      |
 * |------------------------------------|--------------|------------------------------------------------------------|
 * | Thierry Marchand (+33612345680)    | Réglisse     | missing (listed, no care item, no next appointment)        |
 * |                                    | Caramel      | archived (404)                                             |
 * |                                    | Pilou        | secondary owner only, Camille Fontaine is primary (404)    |
 * |                                    | Sésame       | on his second client file: no visit, no vaccination        |
 * |                                    | Brio         | on his second client file: sold (404)                      |
 * | Sophie Garnier (+33612345679)      | Pepito       | CHPPiL renewed without a next due date (the older act had one: no care item) |
 * |                                    | Nala         | weight tie: an Animal weighing on the day of a closed consultation (Animal wins) |
 * | Julien Girard (+33612345678)       | Oslo         | vaccinated (booklet with two acts), weighed                |
 *
 * Inherited from ClientSheetStatesStory and ClientPortalStatesStory: Filou
 * and Tigrou deceased; Garnier's care items overdue (Oscar, Pepito), due
 * soon (Nala, Coco) and up to date (Oscar), more than three across her four
 * alive pets; Oscar weighed more recently than his last closed consultation
 * (his open one today is ignored); Pepito weighed by consultations only;
 * Pixel (Girard, Lyon) with no vaccination and no visit.
 */
final class PortalPetsAnimalStory extends Story
{
    /** Thierry Marchand's second file in Paris (opened at his former address). */
    public const string MARCHAND_SECOND_FILE_ID = '019a3d00-0000-7000-8000-0000000000c1';

    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    private \DateTimeImmutable $today;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        PortalDocumentsStory::load();

        $this->today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ))->setTime(0, 0);

        $this->marchand();
        $this->garnier();
        $this->girard();
    }

    /** Every pet the Portal hides or keeps apart, around Thierry Marchand. */
    private function marchand(): void
    {
        $marchand = ClientSheetStatesStory::CLIENT_NORMAL_ID;

        $reglisse = $this->animal($marchand, 'Réglisse', 'cat', 'female', 'neutered', 'Européen', '-6 years');
        $this->care($reglisse, 'vaccination', 'Typhus-Coryza', '-400 days', '-35 days');
        $this->commandBus->dispatch(new UpdateAnimalLifeCycle(
            clinicId: self::PARIS,
            animalId: $reglisse,
            lifeStatus: 'missing',
            deceasedAt: null,
            missingSince: $this->at('-3 weeks')->format('Y-m-d'),
            deathCause: null,
            bodyDisposal: null,
        ));

        $caramel = $this->animal($marchand, 'Caramel', 'nac', 'male', 'unknown', 'Hamster doré', '-2 years');
        $this->commandBus->dispatch(new ArchiveAnimal(self::PARIS, $caramel));

        // Camille Fontaine's dog, Thierry Marchand only a secondary owner.
        $this->animal(ClientPortalStatesStory::FONTAINE_ID, 'Pilou', 'dog', 'male', 'neutered', 'Bichon frisé', '-7 years', [$marchand]);

        $this->secondFile();

        $sesame = $this->animal(self::MARCHAND_SECOND_FILE_ID, 'Sésame', 'nac', 'female', 'unknown', 'Lapin nain', '-1 year -3 months');
        $this->weigh($sesame, '-2 months', 1350);

        $brio = $this->animal(self::MARCHAND_SECOND_FILE_ID, 'Brio', 'dog', 'male', 'intact', 'Border collie', '-2 years');
        $this->commandBus->dispatch(new UpdateAnimalTransfer(self::PARIS, $brio, 'sold', $this->at('-8 months')->format('Y-m-d'), null));
    }

    private function garnier(): void
    {
        $client = ClientSheetStatesStory::CLIENT_BUSY_ID;

        // The CHPPiL protocol was renewed without a next due date: the older due date never resurfaces.
        $pepito = $this->animalOf($client, 'Pepito');
        $this->care($pepito, 'vaccination', 'CHPPiL', '-400 days', '-35 days');
        $this->care($pepito, 'vaccination', 'CHPPiL', '-153 days', null);

        // Weighed at the desk on the day of her closed consultation (4.3 kg): the Animal record wins.
        $this->weigh($this->animalOf($client, 'Nala'), '-88 days', 4250);
    }

    private function girard(): void
    {
        $oslo = $this->animalOf($this->girardParis(), 'Oslo');
        $this->care($oslo, 'vaccination', 'CHPL', '-58 days', '+307 days');
        $this->care($oslo, 'vaccination', 'Rage', '-58 days', '+307 days');
        $this->weigh($oslo, '-58 days', 24300);
    }

    /** A second file of Thierry Marchand, opened years ago at his former address. */
    private function secondFile(): void
    {
        $since = $this->at('-9 years')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        ClientEntityFactory::new()
            ->withId(self::MARCHAND_SECOND_FILE_ID)
            ->withClinicId(self::PARIS)
            ->withName('Thierry', 'Marchand')
            ->withCivility('mister')
            ->since($since)
            ->withPostalAddress(streetLine1: '5 rue Houdon', city: 'Paris', countryCode: 'FR', postalCode: '75018')
            ->withCommunicationPreferences('sms', ['appointment_reminders' => ['sms']], $since)
            ->active()
            ->create()
        ;
        ContactMethodEntityFactory::new()->forClient(self::MARCHAND_SECOND_FILE_ID)->mobile()->phone(PortalDocumentsStory::MARCHAND_PHONE)->primary()->create();
    }

    /** @param list<string> $secondaryOwners */
    private function animal(string $clientId, string $name, string $species, string $sex, string $reproductiveStatus, string $breed, string $born, array $secondaryOwners = []): string
    {
        $id = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: self::PARIS,
            name: $name,
            species: $species,
            sex: $sex,
            reproductiveStatus: $reproductiveStatus,
            isMixedBreed: false,
            breedName: $breed,
            birthDate: $this->at($born)->format('Y-m-d'),
            color: null,
            photoUrl: null,
            microchipNumber: null,
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
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: $secondaryOwners,
        ));
        \assert(\is_string($id));

        return $id;
    }

    private function care(string $animalId, string $kind, string $label, string $administered, ?string $due): void
    {
        $this->commandBus->dispatch(new RecordPreventiveCare(
            clinicId: self::PARIS,
            animalId: $animalId,
            kind: $kind,
            label: $label,
            administeredOn: $this->at($administered)->format('Y-m-d'),
            batchNumber: null,
            nextDueOn: null === $due ? null : $this->at($due)->format('Y-m-d'),
            authorName: 'Rousseau',
        ));
    }

    private function weigh(string $animalId, string $when, int $grams): void
    {
        $this->commandBus->dispatch(new RecordAnimalWeight(
            clinicId: self::PARIS,
            animalId: $animalId,
            weightGrams: $grams,
            measuredOn: $this->at($when)->format('Y-m-d'),
            bodyConditionScore: null,
            authorName: 'Julie Martin',
        ));
    }

    private function at(string $relative): \DateTimeImmutable
    {
        return $this->today->modify($relative);
    }

    private function animalOf(string $clientId, string $name): string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(a.id) FROM animal__animals a JOIN animal__ownerships o ON o.animal_id = a.id AND o.role = 'primary'
             WHERE o.client_id = ? AND a.name = ? LIMIT 1",
            [Uuid::fromString($clientId)->toBinary(), $name],
        );
        \assert(\is_string($id), \sprintf('The dataset has no animal "%s" for client %s.', $name, $clientId));

        return $id;
    }

    /** Julien Girard of Paris, owner of Oslo (ConsultationDemoStory, demo phone). */
    private function girardParis(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(c.id) FROM client__clients c INNER JOIN client__contact_methods m ON m.client_id = c.id
             WHERE c.clinic_id = ? AND c.last_name = ? AND m.value = ? LIMIT 1',
            [Uuid::fromString(self::PARIS)->toBinary(), 'Girard', PortalUserStory::DEMO_PHONE],
        );
        \assert(\is_string($id));

        return $id;
    }
}
