<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Audit\Domain\ValueObject;

use App\System\Audit\Domain\ValueObject\AuditDetail;
use App\System\Audit\Domain\ValueObject\AuditEntryId;
use PHPUnit\Framework\TestCase;

final class AuditDetailTest extends TestCase
{
    public function testLabelAndValueAreTrimmed(): void
    {
        $detail = AuditDetail::of(' Durée ', ' 18 min ');

        self::assertSame('Durée', $detail->label());
        self::assertSame('18 min', $detail->value());
    }

    public function testLongTextIsCut(): void
    {
        $detail = AuditDetail::of(str_repeat('l', 80), str_repeat('v', 300));

        self::assertSame(60, mb_strlen($detail->label()));
        self::assertSame(255, mb_strlen($detail->value()));
    }

    public function testAnEmptyValueIsAllowed(): void
    {
        self::assertSame('', AuditDetail::of('Commentaire', '')->value());
    }

    public function testALabelIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AuditDetail::of(' ', 'x');
    }

    public function testEqualityComparesLabelAndValue(): void
    {
        self::assertTrue(AuditDetail::of('Mode', 'Lecture seule')->equals(AuditDetail::of('Mode', 'Lecture seule')));
        self::assertFalse(AuditDetail::of('Mode', 'Lecture seule')->equals(AuditDetail::of('Mode', 'Écriture')));
    }

    public function testTheEntryIdIsBuiltFromItsString(): void
    {
        self::assertSame('01960000-0000-7000-8000-000000000e01', AuditEntryId::fromString('01960000-0000-7000-8000-000000000e01')->toString());
    }
}
