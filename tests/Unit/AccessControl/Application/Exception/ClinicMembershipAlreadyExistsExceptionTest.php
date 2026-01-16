<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Exception;

use App\AccessControl\Application\Exception\ClinicMembershipAlreadyExistsException;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipAlreadyExistsExceptionTest extends TestCase
{
    public function testExceptionIsRuntimeException(): void
    {
        $exception = new ClinicMembershipAlreadyExistsException('Test message');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        $exception = new ClinicMembershipAlreadyExistsException('Membership already exists');

        self::assertSame('Membership already exists', $exception->getMessage());
    }

    public function testExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(ClinicMembershipAlreadyExistsException::class);
        $this->expectExceptionMessage('Test exception');

        throw new ClinicMembershipAlreadyExistsException('Test exception');
    }
}
