<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Query\GetClinicGroup;

use App\Clinic\Application\Query\GetClinicGroup\GetClinicGroup;
use App\Clinic\Application\Query\GetClinicGroup\GetClinicGroupHandler;
use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use PHPUnit\Framework\TestCase;

final class GetClinicGroupHandlerTest extends TestCase
{
    public function testReturnsClinicGroupDto(): void
    {
        $group = ClinicGroup::create(
            id: ClinicGroupId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            name: 'Test Group',
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        );

        $repo = $this->createMock(ClinicGroupRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn ($id) => '018f1b1e-1234-7890-abcd-0123456789ab' === $id->toString()))
            ->willReturn($group)
        ;

        $handler = new GetClinicGroupHandler($repo);
        $dto = $handler(new GetClinicGroup('018f1b1e-1234-7890-abcd-0123456789ab'));

        self::assertNotNull($dto);
        self::assertSame('018f1b1e-1234-7890-abcd-0123456789ab', $dto->id);
        self::assertSame('Test Group', $dto->name);
        self::assertSame(ClinicGroupStatus::ACTIVE, $dto->status);
        self::assertSame('2024-01-01T10:00:00+00:00', $dto->createdAt);
    }

    public function testReturnsNullWhenClinicGroupNotFound(): void
    {
        $repo = $this->createMock(ClinicGroupRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $handler = new GetClinicGroupHandler($repo);
        $dto = $handler(new GetClinicGroup('018f1b1e-1234-7890-abcd-0123456789ab'));

        self::assertNull($dto);
    }
}
