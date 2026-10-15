<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Zenstruck\Foundry\Story;

/**
 * The quiet clinic of the group, as the dashboard's « journée calme »
 * mock-up shows it: three appointments today — two seen in the morning, one
 * left for the afternoon — nobody on site, no stock, nothing unpaid. It
 * exercises every empty and calm state of the dashboard.
 */
final class CalmDayClinicStory extends Story
{
    private const string CLINIC = ClinicDataStory::CALM_CLINIC_ID;

    /** [client id, first name, last name, animal id, animal, species, breed, hour, minute, duration, reason] */
    private const array DAY = [
        ['01936e19-c001-7000-8000-000000000c01', 'Karim', 'Bensaïd', '01936e1a-c001-7000-8000-000000000c01', 'Nala', 'dog', 'Berger australien', 9, 0, 20, 'Vaccin CHPPiL + rappel rage'],
        ['01936e19-c002-7000-8000-000000000c02', 'Hélène', 'Ferrand', '01936e1a-c002-7000-8000-000000000c02', 'Mochi', 'cat', 'Européen', 9, 30, 20, 'Contrôle post-stérilisation'],
        ['01936e19-c003-7000-8000-000000000c03', 'Camille', 'Rey', '01936e1a-c003-7000-8000-000000000c03', 'Luna', 'cat', 'Sacré de Birmanie', 14, 0, 20, 'Primo-vaccination chaton'],
    ];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function build(): void
    {
        $vetUser = ClinicUserFactory::repository()->findOneBy(['email' => 'vet@kiveto.local']);
        if (null === $vetUser) {
            throw new \RuntimeException('vet@kiveto.local not found. ClinicVetStory must be loaded first.');
        }

        $parisTz = new \DateTimeZone('Europe/Paris');
        $now     = $this->clock->now()->setTimezone($parisTz);
        $today   = $now->setTime(0, 0);

        foreach (self::DAY as [$clientId, $firstName, $lastName, $animalId, $animal, $species, $breed, $hour, $minute, $duration, $reason]) {
            ClientEntityFactory::new()
                ->withId($clientId)
                ->withClinicId(self::CLINIC)
                ->withName($firstName, $lastName)
                ->active()
                ->create()
            ;

            $factory = AnimalEntityFactory::new()
                ->withId($animalId)
                ->withClinicId(self::CLINIC)
                ->withName($animal)
            ;

            $factory = 'dog' === $species ? $factory->dog() : $factory->cat();

            $factory
                ->withBreed($breed, Species::from($species))
                ->active()
                ->alive()
                ->create()
            ;

            OwnershipEntityFactory::new()
                ->withAnimalId($animalId)
                ->withClientId($clientId)
                ->primary()
                ->active()
                ->create()
            ;

            $start = $today->setTime($hour, $minute);

            // The afternoon booking stays ahead of the clock when the fixtures
            // are loaded after it (the next half hour, an hour from now),
            // as long as the clinic is still open then.
            if ($hour >= 12 && $start < $now->modify('+1 hour')) {
                $later = $now->modify('+1 hour');
                $later = $later->setTime((int) $later->format('G'), (int) $later->format('i') > 30 ? 60 : 30);
                $start = $later <= $today->setTime(17, 30) ? $later : $start;
            }

            $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
                clinicId: self::CLINIC,
                practitionerUserId: $vetUser->getId()->toRfc4122(),
                startsAtUtc: $start->setTimezone(new \DateTimeZone('UTC')),
                durationMinutes: $duration,
                reason: $reason,
                notes: null,
                ownerId: $clientId,
                animalId: $animalId,
            ));
            \assert(\is_string($appointmentId));

            // The morning has been seen; the afternoon is still to come
            // unless the fixtures are loaded after it.
            if ($start->modify(\sprintf('+%d minutes', $duration)) <= $now || $hour < 12) {
                $this->commandBus->dispatch(new CompleteAppointment($appointmentId));
            }
        }
    }
}
