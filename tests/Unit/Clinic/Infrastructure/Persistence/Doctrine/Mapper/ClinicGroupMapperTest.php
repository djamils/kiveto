<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicGroupEntity;
use App\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicGroupMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicGroupMapperTest extends TestCase
{
    private ClinicGroupMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ClinicGroupMapper();
    }

    public function testToDomain(): void
    {
        $entity = new ClinicGroupEntity();
        $entity->setId(Uuid::v7());
        $entity->setName('Test Group');
        $entity->setStatus(ClinicGroupStatus::ACTIVE);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01T10:00:00Z'));

        $domain = $this->mapper->toDomain($entity);

        self::assertSame($entity->getId()->toString(), $domain->id()->toString());
        self::assertSame('Test Group', $domain->name());
        self::assertSame(ClinicGroupStatus::ACTIVE, $domain->status());
        self::assertSame($entity->getCreatedAt(), $domain->createdAt());
    }

    public function testToEntity(): void
    {
        $group = ClinicGroup::create(
            id: ClinicGroupId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            name: 'Test Group',
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );

        $entity = $this->mapper->toEntity($group);

        self::assertSame($group->id()->toString(), $entity->getId()->toString());
        self::assertSame('Test Group', $entity->getName());
        self::assertSame(ClinicGroupStatus::ACTIVE, $entity->getStatus());
        self::assertSame($group->createdAt(), $entity->getCreatedAt());
    }

    public function testRoundTrip(): void
    {
        $entity = new ClinicGroupEntity();
        $entity->setId(Uuid::v7());
        $entity->setName('Test Group');
        $entity->setStatus(ClinicGroupStatus::SUSPENDED);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01T10:00:00Z'));

        $domain     = $this->mapper->toDomain($entity);
        $entityBack = $this->mapper->toEntity($domain);

        self::assertSame($entity->getId()->toString(), $entityBack->getId()->toString());
        self::assertSame($entity->getName(), $entityBack->getName());
        self::assertSame($entity->getStatus(), $entityBack->getStatus());
        self::assertSame($entity->getCreatedAt(), $entityBack->getCreatedAt());
    }
}
