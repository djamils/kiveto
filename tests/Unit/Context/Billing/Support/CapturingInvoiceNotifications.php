<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\InvoiceMessage;
use App\Context\Billing\Application\Port\NotificationRequesterInterface;

final class CapturingInvoiceNotifications implements NotificationRequesterInterface
{
    /** @var list<InvoiceMessage> */
    public array $messages = [];

    public function request(InvoiceMessage $message): string
    {
        $this->messages[] = $message;

        return 'message-' . \count($this->messages);
    }
}
