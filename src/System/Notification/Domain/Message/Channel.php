<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

enum Channel: string
{
    case EMAIL = 'EMAIL';
    case SMS   = 'SMS';
}
