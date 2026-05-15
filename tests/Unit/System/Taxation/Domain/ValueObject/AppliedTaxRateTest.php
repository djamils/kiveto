<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\ValueObject\AppliedTaxRate;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use PHPUnit\Framework\TestCase;

final class AppliedTaxRateTest extends TestCase
{
    public function testAllGettersReturnConstructorValues(): void
    {
        $rateId        = TaxRateId::fromString('fr.standard');
        $regimeId      = TaxRegimeId::fromString('FR');
        $value         = TaxRateValue::ofBasisPoints(2000);
        $effectiveFrom = new \DateTimeImmutable('2014-01-01');
        $source        = 'yaml.fr.standard';

        $applied = AppliedTaxRate::of($rateId, $regimeId, $value, $effectiveFrom, $source);

        self::assertSame('fr.standard', $applied->rateId()->toString());
        self::assertSame('FR', $applied->regimeId()->toString());
        self::assertSame(2000, $applied->value()->basisPoints());
        self::assertSame($effectiveFrom, $applied->effectiveFrom());
        self::assertSame('yaml.fr.standard', $applied->source());
    }
}
