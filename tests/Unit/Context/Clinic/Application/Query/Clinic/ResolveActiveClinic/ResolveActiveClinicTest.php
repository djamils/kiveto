<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic;

use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ResolveActiveClinic;
use PHPUnit\Framework\TestCase;

final class ResolveActiveClinicTest extends TestCase
{
    public function testConstruct(): void
    {
        $query = new ResolveActiveClinic('user-uuid');

        self::assertSame('user-uuid', $query->userId);
    }
}
