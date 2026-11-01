<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\EventSubscriber;

use App\Context\Scheduling\Application\EventSubscriber\ArchivePetCreatedForDeclinedBooking;
use App\Context\Scheduling\Application\Port\BookedPetArchiverInterface;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Tests\Unit\Context\Scheduling\Support\OnlineBookingWorld as W;
use PHPUnit\Framework\TestCase;

/**
 * AC 154: the animal registered for a declined or expired request is
 * archived unless it has another appointment (the Animal adapter also keeps
 * one with a clinical record).
 */
final class ArchivePetCreatedForDeclinedBookingTest extends TestCase
{
    private W $world;

    /** @var \ArrayObject<int, array{string, string}> */
    private \ArrayObject $archived;

    protected function setUp(): void
    {
        $this->world    = new W();
        $this->archived = new \ArrayObject();
    }

    public function testTheAnimalRegisteredForTheRequestIsArchived(): void
    {
        $this->world->existing('2026-04-15 07:00', status: AppointmentStatus::DECLINED, createdPetId: W::ANIMAL);
        // A withdrawn appointment of that animal does not keep it
        $this->world->existing('2026-04-16 07:00', status: AppointmentStatus::CANCELLED, id: '01960000-0000-7000-8000-0000000a0102');

        $this->subscriber()(self::declined());

        self::assertSame([[W::CLINIC, W::ANIMAL]], $this->archived->getArrayCopy());
    }

    public function testAnAnimalWithAnotherAppointmentIsKept(): void
    {
        $this->world->existing('2026-04-15 07:00', status: AppointmentStatus::DECLINED, createdPetId: W::ANIMAL);
        $this->world->existing('2026-04-16 07:00', id: '01960000-0000-7000-8000-0000000a0102');

        $this->subscriber()(self::declined());

        self::assertSame([], $this->archived->getArrayCopy());
    }

    public function testARequestForAKnownAnimalArchivesNothing(): void
    {
        $this->world->existing('2026-04-15 07:00', status: AppointmentStatus::DECLINED);

        $this->subscriber()(self::declined());

        self::assertSame([], $this->archived->getArrayCopy());
    }

    public function testAnUnknownAppointmentArchivesNothing(): void
    {
        $this->subscriber()(self::declined());

        self::assertSame([], $this->archived->getArrayCopy());
    }

    private function subscriber(): ArchivePetCreatedForDeclinedBooking
    {
        $archiver = new class($this->archived) implements BookedPetArchiverInterface {
            /** @param \ArrayObject<int, array{string, string}> $archived */
            public function __construct(private readonly \ArrayObject $archived)
            {
            }

            public function archiveUnlessRecorded(string $clinicId, string $animalId): void
            {
                $this->archived->append([$clinicId, $animalId]);
            }
        };

        return new ArchivePetCreatedForDeclinedBooking($this->world->appointments, $archiver);
    }

    private static function declined(): AppointmentDeclined
    {
        return new AppointmentDeclined(W::BOOKED, W::CLINIC, W::ROUSSEAU, '2026-04-15T07:00:00+00:00', W::OWNER, W::ANIMAL, 'portal', 'expired');
    }
}
