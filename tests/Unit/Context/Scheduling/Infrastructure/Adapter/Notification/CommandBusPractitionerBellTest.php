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
}
