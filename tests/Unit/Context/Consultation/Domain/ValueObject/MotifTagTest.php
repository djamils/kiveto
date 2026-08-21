<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\MotifTag;
use PHPUnit\Framework\TestCase;

final class MotifTagTest extends TestCase
{
    public function testCreateTrimsLabelAndGeneratesId(): void
    {
        $tag = MotifTag::create('  Boiterie postérieure  ');

        self::assertSame('Boiterie postérieure', $tag->getLabel());
        self::assertNotSame('', $tag->getId());
    }

    public function testCreateAcceptsLabelAtMaxLength(): void
    {
        $label = str_repeat('a', 120);

        $tag = MotifTag::create($label);

        self::assertSame($label, $tag->getLabel());
    }

    public function testCreateRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Motif label cannot be empty');

        MotifTag::create('   ');
    }

    public function testCreateRejectsTooLongLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Motif label cannot exceed 120 characters');

        MotifTag::create(str_repeat('a', 121));
    }

    public function testReconstituteKeepsProvidedValues(): void
    {
        $tag = MotifTag::reconstitute('33333333-3333-4333-8333-333333333333', 'Vomissements');

        self::assertSame('33333333-3333-4333-8333-333333333333', $tag->getId());
        self::assertSame('Vomissements', $tag->getLabel());
    }
}
