<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;

final class SequentialUuids implements UuidGeneratorInterface
{
    private int $next = 0;

    public function generate(): string
    {
        return \sprintf('01960000-0000-7000-8000-%012d', ++$this->next);
    }
}
