<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\WithdrawInAppNotification;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « What these entries announced no longer holds » (e.g. a new appointment
 * cancelled): the unread entries posted under that key disappear; entries
 * already read stay in the history.
 */
final readonly class WithdrawInAppNotification implements CommandInterface
{
    public function __construct(public string $idempotencyKey)
    {
    }
}
