<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\BillingLineSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BillingLineSourceTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabel(BillingLineSource $source, string $expected): void
    {
        self::assertSame($expected, $source->label());
    }

    /**
     * @return iterable<string, array{BillingLineSource, string}>
     */
    public static function provideLabelCases(): iterable
    {
        yield 'plan act' => [BillingLineSource::PLAN_ACT, 'Acte'];
        yield 'prescription' => [BillingLineSource::PRESCRIPTION, 'Médicament'];
    }

    public function testEveryCaseIsCovered(): void
    {
        self::assertCount(2, BillingLineSource::cases());
    }
}
