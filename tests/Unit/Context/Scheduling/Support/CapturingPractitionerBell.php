<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\PractitionerBellInterface;

final class CapturingPractitionerBell implements PractitionerBellInterface
{
    /** @var list<array{clinicId: string, userId: string, appointmentId: string, date: string, title: string, detail: string, key: string}> */
    public array $announced = [];

    /** @var list<string> */
    public array $withdrawn = [];

    public function announceNewAppointment(string $clinicId, string $practitionerUserId, string $appointmentId, string $localDate, string $title, string $detail, string $idempotencyKey): void
    {
        $this->announced[] = ['clinicId' => $clinicId, 'userId' => $practitionerUserId, 'appointmentId' => $appointmentId, 'date' => $localDate, 'title' => $title, 'detail' => $detail, 'key' => $idempotencyKey];
    }

    public function withdraw(string $idempotencyKey): void
    {
        $this->withdrawn[] = $idempotencyKey;
    }
}
