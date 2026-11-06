<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Domain;

use App\System\FeatureCatalog\Domain\FeatureStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureStatusTest extends TestCase
{
    #[DataProvider('provideEachStatusIsShownInFrenchCases')]
    public function testEachStatusIsShownInFrench(FeatureStatus $status, string $label): void
    {
        self::assertSame($label, $status->label());
    }

    /**
     * @return iterable<string, array{FeatureStatus, string}>
     */
    public static function provideEachStatusIsShownInFrenchCases(): iterable
    {
        yield 'available' => [FeatureStatus::AVAILABLE, 'Disponible'];

        yield 'beta' => [FeatureStatus::BETA, 'Bêta'];

        yield 'internal' => [FeatureStatus::INTERNAL, 'Interne'];

        yield 'upcoming' => [FeatureStatus::UPCOMING, 'À venir'];
    }
}
