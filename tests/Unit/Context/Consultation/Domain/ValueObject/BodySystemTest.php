<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\BodySystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BodySystemTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabel(BodySystem $system, string $expected): void
    {
        self::assertSame($expected, $system->label());
    }

    /**
     * @return iterable<string, array{BodySystem, string}>
     */
    public static function provideLabelCases(): iterable
    {
        yield 'cardiovascular' => [BodySystem::CARDIOVASCULAR, 'Cardiovasculaire'];
        yield 'respiratory' => [BodySystem::RESPIRATORY, 'Respiratoire'];
        yield 'digestive' => [BodySystem::DIGESTIVE, 'Digestif'];
        yield 'urinary' => [BodySystem::URINARY, 'Urogénital'];
        yield 'locomotor' => [BodySystem::LOCOMOTOR, 'Locomoteur'];
        yield 'neurological' => [BodySystem::NEUROLOGICAL, 'Neurologique'];
        yield 'skin' => [BodySystem::SKIN, 'Cutané'];
        yield 'ophthalmic' => [BodySystem::OPHTHALMIC, 'Ophtalmologique'];
        yield 'dental' => [BodySystem::DENTAL, 'Dentaire'];
        yield 'integument' => [BodySystem::INTEGUMENT, 'Tégument / Carapace'];
    }

    #[DataProvider('provideIconCases')]
    public function testIcon(BodySystem $system, string $expected): void
    {
        self::assertSame($expected, $system->icon());
    }

    /**
     * @return iterable<string, array{BodySystem, string}>
     */
    public static function provideIconCases(): iterable
    {
        yield 'cardiovascular' => [BodySystem::CARDIOVASCULAR, 'heart'];
        yield 'respiratory' => [BodySystem::RESPIRATORY, 'wind'];
        yield 'digestive' => [BodySystem::DIGESTIVE, 'gi'];
        yield 'urinary' => [BodySystem::URINARY, 'droplet'];
        yield 'locomotor' => [BodySystem::LOCOMOTOR, 'bone'];
        yield 'neurological' => [BodySystem::NEUROLOGICAL, 'brain'];
        yield 'skin' => [BodySystem::SKIN, 'sparkle'];
        yield 'ophthalmic' => [BodySystem::OPHTHALMIC, 'eye'];
        yield 'dental' => [BodySystem::DENTAL, 'tooth'];
        yield 'integument' => [BodySystem::INTEGUMENT, 'shell'];
    }

    #[DataProvider('provideDrilldownCases')]
    public function testDrilldown(BodySystem $system, ?string $expected): void
    {
        self::assertSame($expected, $system->drilldown());
    }

    /**
     * @return iterable<string, array{BodySystem, string|null}>
     */
    public static function provideDrilldownCases(): iterable
    {
        yield 'cardiovascular' => [BodySystem::CARDIOVASCULAR, 'cardio'];
        yield 'locomotor' => [BodySystem::LOCOMOTOR, 'loco'];
        yield 'skin' => [BodySystem::SKIN, 'derma'];
        yield 'integument' => [BodySystem::INTEGUMENT, 'derma'];
        yield 'respiratory' => [BodySystem::RESPIRATORY, null];
        yield 'digestive' => [BodySystem::DIGESTIVE, null];
        yield 'urinary' => [BodySystem::URINARY, null];
        yield 'neurological' => [BodySystem::NEUROLOGICAL, null];
        yield 'ophthalmic' => [BodySystem::OPHTHALMIC, null];
        yield 'dental' => [BodySystem::DENTAL, null];
    }

    #[DataProvider('provideEveryCaseExposesMetadataCases')]
    public function testEveryCaseExposesMetadata(BodySystem $system): void
    {
        self::assertNotSame('', $system->label());
        self::assertNotSame('', $system->icon());
    }

    /**
     * @return iterable<string, array{BodySystem}>
     */
    public static function provideEveryCaseExposesMetadataCases(): iterable
    {
        foreach (BodySystem::cases() as $system) {
            yield $system->value => [$system];
        }
    }
}
