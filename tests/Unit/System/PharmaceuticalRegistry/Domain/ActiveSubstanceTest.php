<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain;

use App\System\PharmaceuticalRegistry\Domain\ActiveSubstance;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use PHPUnit\Framework\TestCase;

final class ActiveSubstanceTest extends TestCase
{
    public function testNormalizeLabelConvertsToLowercase(): void
    {
        $normalized = ActiveSubstance::normalizeLabel("MALÉATE D'OCLACITINIB");

        self::assertSame("maléate d'oclacitinib", $normalized);
    }

    public function testNormalizeLabelCollapsesSpaces(): void
    {
        $normalized = ActiveSubstance::normalizeLabel('  oclacitinib   maleate  ');

        self::assertSame('oclacitinib maleate', $normalized);
    }

    public function testNormalizeLabelTrims(): void
    {
        $normalized = ActiveSubstance::normalizeLabel('  abc  ');

        self::assertSame('abc', $normalized);
    }

    public function testCreateSetsLabelNormalized(): void
    {
        $substance = ActiveSubstance::create(
            id: ActiveSubstanceId::fromString('018f1b1e-0000-7000-8000-000000000001'),
            label: 'OCLACITINIB MALEATE',
            innCode: null,
            now: new \DateTimeImmutable(),
        );

        self::assertSame('oclacitinib maleate', $substance->labelNormalized());
    }
}
