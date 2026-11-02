<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\NewClientConfirmationInterface;

/**
 * ClientPortal's ConfirmBookingRequest seen from Scheduling: without
 * `$onConfirm`, no pending booking request holds the appointment (false);
 * with it, the request is confirmed (the closure plays
 * `ConfirmNewClientAppointment`) and true is answered.
 */
final class FakeNewClientConfirmation implements NewClientConfirmationInterface
{
    /** @var list<array{string, string, ?string, bool}> clinic, appointment, animal, silent */
    public array $calls = [];

    /** @var (\Closure(string, string, bool): void)|null */
    public ?\Closure $onConfirm = null;

    public function confirm(string $clinicId, string $appointmentId, ?string $animalId, bool $silent): bool
    {
        $this->calls[] = [$clinicId, $appointmentId, $animalId, $silent];

        if (null === $this->onConfirm) {
            return false;
        }

        ($this->onConfirm)($clinicId, $appointmentId, $silent);

        return true;
    }
}
