<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Notification;

use App\Context\Scheduling\Application\Port\PractitionerBellInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\System\Notification\Application\Command\WithdrawInAppNotification\WithdrawInAppNotification;

/**
 * Notification's inbox commands. The entry opens the agenda on the
 * appointment's day (`rdv` selects the appointment there).
 */
final readonly class CommandBusPractitionerBell implements PractitionerBellInterface
{
    public const string ROUTE = 'clinic_scheduling_agenda';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function announceNewAppointment(
        string $clinicId,
        string $practitionerUserId,
        string $appointmentId,
        string $localDate,
        string $title,
        string $detail,
        string $idempotencyKey,
    ): void {
        $this->commandBus->dispatch(new PostInAppNotification(
            clinicId: $clinicId,
            userIds: [$practitionerUserId],
            kind: 'NEW_APPOINTMENT',
            title: $title,
            detail: $detail,
            route: self::ROUTE,
            routeParams: ['view' => 'day', 'date' => $localDate, 'rdv' => $appointmentId],
            idempotencyKey: $idempotencyKey,
        ));
    }

    public function withdraw(string $idempotencyKey): void
    {
        $this->commandBus->dispatch(new WithdrawInAppNotification($idempotencyKey));
    }
}
