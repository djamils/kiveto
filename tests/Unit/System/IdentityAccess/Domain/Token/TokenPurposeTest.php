<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Token;

use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use PHPUnit\Framework\TestCase;

final class TokenPurposeTest extends TestCase
{
    public function testLifetimes(): void
    {
        $start = new \DateTimeImmutable('2026-10-26 10:00:00');

        self::assertSame('2026-10-29 10:00', $start->add(TokenPurpose::INVITATION->lifetime())->format('Y-m-d H:i'));
        self::assertSame('2026-10-26 10:30', $start->add(TokenPurpose::PASSWORD_RESET->lifetime())->format('Y-m-d H:i'));
    }
}
