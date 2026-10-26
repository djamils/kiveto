<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\WithdrawNewAppointmentNotice;

use App\Context\Scheduling\Application\Command\PostNewAppointmentNotice\PostNewAppointmentNoticeHandler;
use App\Context\Scheduling\Application\Port\PractitionerBellInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A read entry stays in the history; only an unread one disappears.
 */
#[AsMessageHandler]
final readonly class WithdrawNewAppointmentNoticeHandler
{
    public function __construct(private PractitionerBellInterface $bell)
    {
    }

    public function __invoke(WithdrawNewAppointmentNotice $command): void
    {
        $this->bell->withdraw(\sprintf(PostNewAppointmentNoticeHandler::KEY, $command->appointmentId));
    }
}
