<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\ValueObject;

use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use PHPUnit\Framework\TestCase;

final class UserStatusTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('PENDING', UserStatus::PENDING->value);
        self::assertSame('ACTIVE', UserStatus::ACTIVE->value);
        self::assertSame('DISABLED', UserStatus::DISABLED->value);
    }
}
