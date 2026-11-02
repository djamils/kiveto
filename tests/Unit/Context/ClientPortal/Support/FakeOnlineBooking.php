<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;

/** Records every call; a new-client slot answers APPOINTMENT, or throws what `refusal` holds. */
final class FakeOnlineBooking implements OnlineBookingInterface
{
    public const string APPOINTMENT = '01960000-0000-7000-8000-0000000aa001';

    /** @var list<list<mixed>> */
    public array $calls = [];

    public ?\Throwable $refusal = null;

    public string $newClientAppointment = self::APPOINTMENT;

    public function book(string $clinicId, string $motifId, string $practitionerId, \DateTimeImmutable $startsAt, string $ownerId, string $animalId, ?string $createdPetId = null): string
    {
        throw new \LogicException('Not a new-client booking.');
    }

    public function reschedule(string $clinicId, string $appointmentId, string $practitionerId, \DateTimeImmutable $startsAt): string
    {
        throw new \LogicException('Not a new-client booking.');
    }

    public function cancel(string $clinicId, string $appointmentId, ?string $reason): string
    {
        throw new \LogicException('Not a new-client booking.');
    }

    public function requestNewClientSlot(string $clinicId, string $motifId, string $practitionerId, \DateTimeImmutable $startsAt): string
    {
        $this->calls[] = ['request', $clinicId, $motifId, $practitionerId, $startsAt->format(\DATE_ATOM)];

        if (null !== $this->refusal) {
            throw $this->refusal;
        }

        return $this->newClientAppointment;
    }

    public function confirmNewClient(string $clinicId, string $appointmentId, string $ownerId, string $animalId, bool $silent = false): void
    {
        $this->calls[] = $silent ? ['confirm', $clinicId, $appointmentId, $ownerId, $animalId, 'silent'] : ['confirm', $clinicId, $appointmentId, $ownerId, $animalId];

        if (null !== $this->refusal) {
            throw $this->refusal;
        }
    }

    public function decline(string $clinicId, string $appointmentId): void
    {
        $this->calls[] = ['decline', $clinicId, $appointmentId];
    }

    public function withdrawPending(string $clinicId, string $appointmentId): void
    {
        $this->calls[] = ['withdraw', $clinicId, $appointmentId];
    }
}
