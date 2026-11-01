<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\WithdrawPendingBookingNotice;

use App\Context\Scheduling\Application\Command\PostPendingBookingNotice\PostPendingBookingNoticeHandler;
use App\Context\Scheduling\Application\Port\PractitionerBellInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A read entry stays in the history; only an unread one disappears.
 */
#[AsMessageHandler]
final readonly class WithdrawPendingBookingNoticeHandler
{
    public function __construct(private PractitionerBellInterface $bell)
    {
    }

    public function __invoke(WithdrawPendingBookingNotice $command): void
    {
        $this->bell->withdraw(\sprintf(PostPendingBookingNoticeHandler::KEY, $command->appointmentId));
    }
}
