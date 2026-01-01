<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

final class UserTypeTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame('CLINIC', UserType::CLINIC->value);
        self::assertSame('PORTAL', UserType::PORTAL->value);
        self::assertSame('BACKOFFICE', UserType::BACKOFFICE->value);
    }
}
