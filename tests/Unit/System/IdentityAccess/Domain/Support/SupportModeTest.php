<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Support;

use App\System\IdentityAccess\Domain\Support\SupportMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupportModeTest extends TestCase
{
    #[DataProvider('provideEachModeIsShownInFrenchCases')]
    public function testEachModeIsShownInFrench(SupportMode $mode, string $label): void
    {
        self::assertSame($label, $mode->label());
    }

    /**
     * @return iterable<string, array{SupportMode, string}>
     */
    public static function provideEachModeIsShownInFrenchCases(): iterable
    {
        yield 'read' => [SupportMode::READ, 'Lecture seule'];

        yield 'write' => [SupportMode::WRITE, 'Lecture et écriture'];
    }
}
