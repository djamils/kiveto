<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic;

use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ActiveClinicResultType;
use PHPUnit\Framework\TestCase;

final class ActiveClinicResultTypeTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame('none', ActiveClinicResultType::NONE->value);
        self::assertSame('single', ActiveClinicResultType::SINGLE->value);
        self::assertSame('multiple', ActiveClinicResultType::MULTIPLE->value);
        self::assertCount(3, ActiveClinicResultType::cases());
    }
}
