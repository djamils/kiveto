<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Animal\Story\PortalPetsAnimalStory;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\ClientPortal\Story\ClientPortalStatesStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The appointments behind the Portal home `nextAppointment` (slice 3):
 *
 * | Account (phone)                 | Clinic | Home `nextAppointment`                                                    |
 * |---------------------------------|--------|---------------------------------------------------------------------------|
 * | Julien Girard (+33612345678)    | Paris  | Oslo in 9 days, « Vaccination » (a cancelled one in 2 days is skipped)     |
 * |                                 | Lyon   | none: Pixel only missed an appointment three weeks ago                    |
 * | Sophie Garnier (+33612345679)   | Paris  | Oscar in 3 days (ClientSheetStatesStory); her appointment tomorrow without |
 * |                                 |        | animal is skipped                                                         |
 * | Thierry Marchand (+33612345680) | Paris  | Gaston in 12 days (ClientSheetStatesStory); the earlier ones of Réglisse   |
 * |                                 |        | (missing) and Brio (sold) are skipped                                     |
 */
final class PortalPetsAppointmentsStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    private \DateTimeImmutable $today;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        PortalPetsAnimalStory::load();

        $this->today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ))->setTime(0, 0);

        $rousseau = $this->user('vet@kiveto.local');
        $dupont   = $this->user('contractor@kiveto.local');

        $girard = $this->clientWithPhone(self::PARIS, 'Girard', PortalUserStory::DEMO_PHONE);
        $oslo   = $this->animalOf($girard, 'Oslo');
        $this->appointment(self::PARIS, $girard, $oslo, $rousseau, '+2 days 11:00', 'Contrôle', AppointmentStatus::CANCELLED);
        $this->appointment(self::PARIS, $girard, $oslo, $rousseau, '+9 days 10:30', 'Rappel vaccin CHPL', AppointmentStatus::PLANNED);

        $pixel = $this->animalOf(ClientPortalStatesStory::GIRARD_LYON_ID, 'Pixel');
        $this->appointment(self::LYON, ClientPortalStatesStory::GIRARD_LYON_ID, $pixel, $this->user('vet.lyon@kiveto.local'), '-20 days 16:00', 'Consultation', AppointmentStatus::NO_SHOW);

        $this->appointment(self::PARIS, ClientSheetStatesStory::CLIENT_BUSY_ID, null, $dupont, '+1 day 12:00', 'Conseil nutrition', AppointmentStatus::PLANNED);

        $marchand = ClientSheetStatesStory::CLIENT_NORMAL_ID;
        $this->appointment(self::PARIS, $marchand, $this->animalOf($marchand, 'Réglisse'), $rousseau, '+2 days 18:00', 'Rappel vaccin typhus', AppointmentStatus::PLANNED);
        $this->appointment(self::PARIS, PortalPetsAnimalStory::MARCHAND_SECOND_FILE_ID, $this->animalOf(PortalPetsAnimalStory::MARCHAND_SECOND_FILE_ID, 'Brio'), $dupont, '+1 day 09:00', 'Consultation', AppointmentStatus::PLANNED);
    }

    private function appointment(string $clinicId, string $clientId, ?string $animalId, string $vetUserId, string $when, string $reason, AppointmentStatus $status): void
    {
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId($vetUserId)
            ->startingAt($this->today->modify($when)->setTimezone(new \DateTimeZone('UTC')))
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $entity) use ($clientId, $animalId, $reason): void {
                $entity->setOwnerId(Uuid::fromString($clientId));
                $entity->setAnimalId(null === $animalId ? null : Uuid::fromString($animalId));
                $entity->setReason($reason);
            })
            ->create()
        ;
    }

    private function user(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

        if (null === $user) {
            throw new \RuntimeException(\sprintf('%s user not found. The staff stories must be loaded first.', $email));
        }

        return $user->getId()->toRfc4122();
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

    private function clientWithPhone(string $clinicId, string $lastName, string $phone): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(c.id) FROM client__clients c INNER JOIN client__contact_methods m ON m.client_id = c.id
             WHERE c.clinic_id = ? AND c.last_name = ? AND m.value = ? LIMIT 1',
            [Uuid::fromString($clinicId)->toBinary(), $lastName, $phone],
        );
        \assert(\is_string($id));

        return $id;
    }
}
