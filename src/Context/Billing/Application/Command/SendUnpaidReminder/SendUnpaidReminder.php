<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\SendUnpaidReminder;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The messages of one logged REMINDER dispatch. Queued on `async` by
 * RemindUnpaidInvoices.
 */
final readonly class SendUnpaidReminder implements CommandInterface
{
    public function __construct(public string $dispatchId)
    {
    }
}
