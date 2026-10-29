<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\AppointmentMessage;
use App\Context\Scheduling\Application\Port\NotificationRequesterInterface;

final class CapturingNotifications implements NotificationRequesterInterface
{
    /** @var list<AppointmentMessage> */
    public array $messages = [];

    public function request(AppointmentMessage $message): void
    {
        $this->messages[] = $message;
    }

    public function last(): AppointmentMessage
    {
        return $this->messages[array_key_last($this->messages) ?? throw new \LogicException('No message requested.')];
    }
}
