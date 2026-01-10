<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory;

use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctrineUserFactoryTest extends TestCase
{
    /**
     * @param class-string<object> $expectedClass
     */
    #[DataProvider('provideCreateForTypeReturnsExpectedEntityCases')]
    public function testCreateForTypeReturnsExpectedEntity(UserType $type, string $expectedClass): void
    {
        $factory = new DoctrineUserFactory();

        $entity = $factory->createForType($type);

        self::assertInstanceOf($expectedClass, $entity);
    }

    /**
     * @return array<string, array{UserType, class-string}>
     */
    public static function provideCreateForTypeReturnsExpectedEntityCases(): iterable
    {
        return [
            'clinic'     => [UserType::CLINIC, ClinicUserEntity::class],
            'portal'     => [UserType::PORTAL, PortalUserEntity::class],
            'backoffice' => [UserType::BACKOFFICE, BackofficeUserEntity::class],
        ];
    }
}
