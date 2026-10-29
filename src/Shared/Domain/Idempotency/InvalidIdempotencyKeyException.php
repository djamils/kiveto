<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

final class InvalidIdempotencyKeyException extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct(\sprintf('An idempotency key must be 1 to %d visible ASCII characters.', IdempotencyKey::MAX_LENGTH));
    }
}
