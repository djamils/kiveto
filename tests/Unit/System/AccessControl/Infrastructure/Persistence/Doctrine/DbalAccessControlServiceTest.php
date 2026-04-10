<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Infrastructure\Persistence\Doctrine;

use App\System\AccessControl\Domain\ValueObject\Permission;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\DbalAccessControlService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class DbalAccessControlServiceTest extends TestCase
{
    public function testIsGrantedReturnsTrueWhenRowFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn('1')
        ;

        $service = new DbalAccessControlService($connection);

        self::assertTrue($service->isGranted(
            SubjectId::fromString('01912345-6789-7abc-8def-0123456789ab'),
            TenantId::fromString('01912345-6789-7abc-8def-0123456789ac'),
            Permission::fromString('create_prescription'),
        ));
    }

    public function testIsGrantedReturnsFalseWhenNoRow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn(false)
        ;

        $service = new DbalAccessControlService($connection);

        self::assertFalse($service->isGranted(
            SubjectId::fromString('01912345-6789-7abc-8def-0123456789ab'),
            TenantId::fromString('01912345-6789-7abc-8def-0123456789ac'),
            Permission::fromString('nonexistent_permission'),
        ));
    }
}
