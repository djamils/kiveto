<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ResolveActiveClinic;

use App\AccessControl\Application\Query\ResolveActiveClinic\ActiveClinicResultType;
use PHPUnit\Framework\TestCase;

final class ActiveClinicResultTypeTest extends TestCase
{
    public function testEnumHasNoneCase(): void
    {
        self::assertSame('none', ActiveClinicResultType::NONE->value);
    }

    public function testEnumHasSingleCase(): void
    {
        self::assertSame('single', ActiveClinicResultType::SINGLE->value);
    }

    public function testEnumHasMultipleCase(): void
    {
        self::assertSame('multiple', ActiveClinicResultType::MULTIPLE->value);
    }

    public function testFromStringReturnsCorrectEnum(): void
    {
        self::assertSame(ActiveClinicResultType::NONE, ActiveClinicResultType::from('none'));
        self::assertSame(ActiveClinicResultType::SINGLE, ActiveClinicResultType::from('single'));
        self::assertSame(ActiveClinicResultType::MULTIPLE, ActiveClinicResultType::from('multiple'));
    }

    public function testAllCasesReturnsThreeCases(): void
    {
        $cases = ActiveClinicResultType::cases();

        self::assertCount(3, $cases);
        self::assertContains(ActiveClinicResultType::NONE, $cases);
        self::assertContains(ActiveClinicResultType::SINGLE, $cases);
        self::assertContains(ActiveClinicResultType::MULTIPLE, $cases);
    }
}
