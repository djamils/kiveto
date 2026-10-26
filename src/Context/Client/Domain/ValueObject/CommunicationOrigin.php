<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * Who wrote a journal entry: the staff (an exchange they logged) or Kiveto
 * (a message it sent on its own — confirmation, reminder, invoice…).
 */
enum CommunicationOrigin: string
{
    case MANUAL    = 'manual';
    case AUTOMATED = 'automated';
}
