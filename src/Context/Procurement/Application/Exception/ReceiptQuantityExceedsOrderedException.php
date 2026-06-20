<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class ReceiptQuantityExceedsOrderedException extends \RuntimeException
{
    public function __construct(string $lineId, string $ordered, string $alreadyReceived, string $attempted)
    {
        parent::__construct(\sprintf(
            'Cannot receive %s for line "%s": ordered=%s, already received=%s.',
            $attempted,
            $lineId,
            $ordered,
            $alreadyReceived,
        ));
    }
}
