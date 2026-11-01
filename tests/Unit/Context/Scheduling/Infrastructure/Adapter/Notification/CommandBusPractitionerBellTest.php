<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\Notification;

use App\Context\Scheduling\Infrastructure\Adapter\Notification\CommandBusPractitionerBell;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\System\Notification\Application\Command\WithdrawInAppNotification\WithdrawInAppNotification;
use PHPUnit\Framework\TestCase;

final class CommandBusPractitionerBellTest extends TestCase
{
    public function testTheNoticeOpensTheAgendaOnTheAppointment(): void
    {
        $bus = new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return 1;
            }
        };
        $bell = new CommandBusPractitionerBell($bus);

        $bell->announceNewAppointment('clinic-1', 'vet-1', 'appointment-1', '2026-10-22', 'Nouveau RDV · Milo', 'Sophie Dubois · jeudi 22/10 à 9h30', 'new-appointment:appointment-1');
        $bell->withdraw('new-appointment:appointment-1');

        [$post, $withdraw] = $bus->commands;
        self::assertInstanceOf(PostInAppNotification::class, $post);
        self::assertSame(
            ['clinic-1', ['vet-1'], 'NEW_APPOINTMENT', 'Nouveau RDV · Milo', 'Sophie Dubois · jeudi 22/10 à 9h30', 'clinic_scheduling_agenda', ['view' => 'day', 'date' => '2026-10-22', 'rdv' => 'appointment-1'], 'new-appointment:appointment-1', null],
            [$post->clinicId, $post->userIds, $post->kind, $post->title, $post->detail, $post->route, $post->routeParams, $post->idempotencyKey, $post->actorInitials],
        );
        self::assertInstanceOf(WithdrawInAppNotification::class, $withdraw);
        self::assertSame('new-appointment:appointment-1', $withdraw->idempotencyKey);
    }

    public function testOnlineBookingEntriesOpenTheAgendaOnTheAppointment(): void
    {
        $bus = new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return 1;
            }
        };
        $bell = new CommandBusPractitionerBell($bus);

        $bell->announcePendingBooking('clinic-1', 'vet-1', 'appointment-1', '2026-10-22', 'RDV en ligne à confirmer · Milo', 'Sophie Dubois · jeudi 22/10 à 9h30', 'online-booking-pending:appointment-1');
        // The old and the new practitioner, once each
        $bell->announceOnlineChange('clinic-1', ['vet-1', 'vet-2', 'vet-1'], 'appointment-1', '2026-10-23', 'RDV modifié en ligne · Milo', 'Sophie Dubois · vendredi 23/10 à 11h00', 'online-booking-changed:appointment-1:2');

        [$pending, $changed] = $bus->commands;
        self::assertInstanceOf(PostInAppNotification::class, $pending);
        self::assertSame(
            ['clinic-1', ['vet-1'], 'ONLINE_BOOKING_PENDING', 'RDV en ligne à confirmer · Milo', 'clinic_scheduling_agenda', ['view' => 'day', 'date' => '2026-10-22', 'rdv' => 'appointment-1'], 'online-booking-pending:appointment-1'],
            [$pending->clinicId, $pending->userIds, $pending->kind, $pending->title, $pending->route, $pending->routeParams, $pending->idempotencyKey],
        );
        self::assertInstanceOf(PostInAppNotification::class, $changed);
        self::assertSame(
            [['vet-1', 'vet-2'], 'ONLINE_BOOKING_CHANGED', 'RDV modifié en ligne · Milo', 'Sophie Dubois · vendredi 23/10 à 11h00', ['view' => 'day', 'date' => '2026-10-23', 'rdv' => 'appointment-1'], 'online-booking-changed:appointment-1:2'],
            [$changed->userIds, $changed->kind, $changed->title, $changed->detail, $changed->routeParams, $changed->idempotencyKey],
        );
    }
}
