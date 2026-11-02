<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\BookedPetMarkerEntity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\PetChangeRequestEntity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\PortalLockEntity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\PrescriptionRenewalRequestEntity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\PreVisitQuestionnaireEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * These entities only describe the schema (rows are written in SQL by the
 * DBAL repositories): building one with a value per parameter is all there
 * is to check.
 */
final class SchemaOnlyEntitiesTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideAnEntityIsBuiltFromOneValuePerColumnCases(): iterable
    {
        foreach ([BookedPetMarkerEntity::class, PetChangeRequestEntity::class, PortalLockEntity::class, PreVisitQuestionnaireEntity::class, PrescriptionRenewalRequestEntity::class] as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideAnEntityIsBuiltFromOneValuePerColumnCases')]
    public function testAnEntityIsBuiltFromOneValuePerColumn(string $class): void
    {
        $constructor = new \ReflectionMethod($class, '__construct');
        $arguments   = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            \assert($type instanceof \ReflectionNamedType);
            $arguments[$parameter->getName()] = match ($type->getName()) {
                Uuid::class               => Uuid::v7(),
                \DateTimeImmutable::class => new \DateTimeImmutable('2026-11-02 10:00:00'),
                'int'                     => 1,
                'bool'                    => true,
                'array'                   => ['k' => 'v'],
                default                   => 'value',
            };
        }

        $entity = new \ReflectionClass($class)->newInstanceArgs($arguments);

        foreach ($arguments as $name => $value) {
            self::assertSame($value, $entity->{$name}, $name);
        }
    }
}
