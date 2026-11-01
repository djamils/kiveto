<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\BookingRequestEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalBookingRequestRepositoryTest extends KernelTestCase
{
    private const string R1      = '01960000-0000-7000-8000-0000000eb001';
    private const string R2      = '01960000-0000-7000-8000-0000000eb002';
    private const string R3      = '01960000-0000-7000-8000-0000000eb003';
    private const string USER    = '01960000-0000-7000-8000-0000000eb101';
    private const string USER2   = '01960000-0000-7000-8000-0000000eb102';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000eb201';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000eb202';
    private const string LISTING = '01960000-0000-7000-8000-0000000eb301';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000eb401';

    public function testARequestIsReadBackAndResolved(): void
    {
        $repository = $this->repository();
        $repository->save($this->request(self::R1, self::USER, self::CLINIC, '2026-11-01 09:00'));

        $found = $repository->findInClinic(self::CLINIC, self::R1);
        self::assertNotNull($found);
        self::assertSame(
            [self::R1, self::USER, self::CLINIC, self::LISTING, self::R1 . '', 'Première consultation', '2026-11-05 13:30', 'UTC', 'Pixel', 'cat', 'Sophie', 'Martin', '+33612345678', BookingRequestStatus::PENDING, null, null, '2026-11-01 09:00', null],
            [$found->id()->toString(), $found->userId(), $found->clinicId(), $found->listingId(), $found->appointmentId(), $found->motifLabel(), $found->startAt()->format('Y-m-d H:i'), $found->startAt()->getTimezone()->getName(), $found->newPetName(), $found->newPetSpecies(), $found->requesterFirstName(), $found->requesterLastName(), $found->requesterPhone(), $found->status(), $found->declineReason(), $found->clientId(), $found->createdAt()->format('Y-m-d H:i'), $found->resolvedAt()],
        );

        $found->confirm(self::CLIENT, new \DateTimeImmutable('2026-11-02 10:00', new \DateTimeZone('UTC')));
        $repository->save($found);
        $confirmed = $repository->findInClinicForUpdate(self::CLINIC, self::R1);
        self::assertSame([BookingRequestStatus::CONFIRMED, self::CLIENT, '2026-11-02 10:00'], [$confirmed?->status(), $confirmed?->clientId(), $confirmed?->resolvedAt()?->format('Y-m-d H:i')]);

        $repository->save($declined = $this->request(self::R2, self::USER, self::CLINIC, '2026-11-01 10:00'));
        $declined->decline(DeclineReason::EXPIRED, new \DateTimeImmutable('2026-11-02 10:00'));
        $repository->save($declined);
        self::assertSame(DeclineReason::EXPIRED, $repository->findByAppointmentForUpdate(self::R2)?->declineReason());

        self::assertNull($repository->findInClinic(self::CLINIC2, self::R1));
        self::assertNull($repository->findInClinic(self::CLINIC, 'nope'));
        self::assertNull($repository->findByAppointmentForUpdate('nope'));
        self::assertNull($repository->findByAppointmentForUpdate(self::R3));
        self::assertSame(self::R1, $repository->findOfUser(self::USER, self::R1)?->id()->toString());
        self::assertNull($repository->findOfUser(self::USER2, self::R1));
        self::assertNull($repository->findOfUser(self::USER, 'nope'));
    }

    public function testTheListsOfAnAccountAndOfAClinic(): void
    {
        $repository = $this->repository();
        $repository->save($this->request(self::R1, self::USER, self::CLINIC, '2026-11-01 09:00'));
        $repository->save($this->request(self::R2, self::USER, self::CLINIC2, '2026-11-01 10:00'));
        $repository->save($this->request(self::R3, self::USER2, self::CLINIC, '2026-11-01 11:00'));
        $confirmed = $this->request('01960000-0000-7000-8000-0000000eb004', self::USER, self::CLINIC, '2026-11-01 12:00');
        $confirmed->confirm(null, new \DateTimeImmutable('2026-11-02'));
        $repository->save($confirmed);

        self::assertSame([self::R1, self::R2], self::ids($repository->findPendingByUserForUpdate(self::USER)));
        self::assertSame([self::R1, self::R3], self::ids($repository->findPendingByClinic(self::CLINIC)));
        self::assertSame([self::R1, self::R2, '01960000-0000-7000-8000-0000000eb004'], self::ids($repository->findByUser(self::USER)));
        self::assertSame(['01960000-0000-7000-8000-0000000eb004', self::R2], self::ids($repository->findPageByUser(self::USER, null, null, 2)));
        self::assertSame([self::R1], self::ids($repository->findPageByUser(self::USER, new \DateTimeImmutable('2026-11-01 10:00'), self::R2, 2)));

        $repository->scrubUser(self::USER);
        $scrubbed = $repository->findInClinic(self::CLINIC, self::R1);
        self::assertSame([null, null, null, null, null], [$scrubbed?->requesterFirstName(), $scrubbed?->requesterLastName(), $scrubbed?->requesterPhone(), $scrubbed?->newPetName(), $scrubbed?->newPetSpecies()]);
        self::assertSame('Pixel', $repository->findInClinic(self::CLINIC, self::R3)?->newPetName());

        // The Doctrine entity only carries the schema
        $entity = new BookingRequestEntity(Uuid::v7(), Uuid::v7(), Uuid::v7(), Uuid::v7(), Uuid::v7(), 'Consultation', new \DateTimeImmutable(), null, null, null, null, null, 'pending', null, null, new \DateTimeImmutable(), null);
        self::assertSame('pending', $entity->status);
    }

    /**
     * @param list<BookingRequest> $requests
     *
     * @return list<string>
     */
    private static function ids(array $requests): array
    {
        return array_map(static fn (BookingRequest $r): string => $r->id()->toString(), $requests);
    }

    private function request(string $id, string $userId, string $clinicId, string $createdAt): BookingRequest
    {
        $utc = new \DateTimeZone('UTC');

        return BookingRequest::request(
            BookingRequestId::fromString($id),
            $userId,
            $clinicId,
            self::LISTING,
            $id,
            'Première consultation',
            new \DateTimeImmutable('2026-11-05 14:30', new \DateTimeZone('Europe/Paris')),
            'Pixel',
            'cat',
            'Sophie',
            'Martin',
            '+33612345678',
            new \DateTimeImmutable($createdAt, $utc),
        );
    }

    private function repository(): BookingRequestRepositoryInterface
    {
        $repository = self::getContainer()->get(BookingRequestRepositoryInterface::class);
        \assert($repository instanceof BookingRequestRepositoryInterface);

        return $repository;
    }
}
