<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * One channel of one consent kind granted or withdrawn: a history row.
 */
final readonly class ConsentChange
{
    public function __construct(
        public ConsentKind $kind,
        public ContactChannel $channel,
        public ConsentChangeAction $action,
    ) {
    }
}
