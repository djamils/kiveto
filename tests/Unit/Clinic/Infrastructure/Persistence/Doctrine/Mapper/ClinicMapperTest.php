<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use App\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicMapper;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicMapperTest extends TestCase
{
    private ClinicMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ClinicMapper();
    }

    public function testToDomainWithClinicGroup(): void
    {
        $entity = new ClinicEntity();
        $entity->setId(Uuid::v7());
        $entity->setName('Test Clinic');
        $entity->setSlug('test-clinic');
        $entity->setTimeZone('Europe/Paris');
        $entity->setLocale('fr-FR');
        $entity->setStatus(ClinicStatus::ACTIVE);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01T10:00:00Z'));
        $entity->setUpdatedAt(new \DateTimeImmutable('2024-01-02T10:00:00Z'));
        $entity->setClinicGroupId(Uuid::v7());

        $domain = $this->mapper->toDomain($entity);

        self::assertSame($entity->getId()->toString(), $domain->id()->toString());
        self::assertSame('Test Clinic', $domain->name());
        self::assertSame('test-clinic', $domain->slug()->toString());
        self::assertSame('Europe/Paris', $domain->timeZone()->toString());
        self::assertSame('fr-FR', $domain->locale()->toString());
        self::assertSame(ClinicStatus::ACTIVE, $domain->status());
        self::assertSame($entity->getCreatedAt(), $domain->createdAt());
        self::assertSame($entity->getUpdatedAt(), $domain->updatedAt());
        self::assertNotNull($domain->clinicGroupId());
        self::assertSame($entity->getClinicGroupId()->toString(), $domain->clinicGroupId()->toString());
    }

    public function testToDomainWithoutClinicGroup(): void
    {
        $entity = new ClinicEntity();
        $entity->setId(Uuid::v7());
        $entity->setName('Test Clinic');
        $entity->setSlug('test-clinic');
        $entity->setTimeZone('Europe/Paris');
        $entity->setLocale('fr-FR');
        $entity->setStatus(ClinicStatus::ACTIVE);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01T10:00:00Z'));
        $entity->setUpdatedAt(new \DateTimeImmutable('2024-01-02T10:00:00Z'));
        $entity->setClinicGroupId(null);

        $domain = $this->mapper->toDomain($entity);

        self::assertNull($domain->clinicGroupId());
    }

    public function testToEntityWithClinicGroup(): void
    {
        $clinic = Clinic::create(
            id: ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            clinicGroupId: ClinicGroupId::fromString('018f1b1e-9999-7890-abcd-0123456789ab'),
        );

        $entity = $this->mapper->toEntity($clinic);

        self::assertSame($clinic->id()->toString(), $entity->getId()->toString());
        self::assertSame('Test Clinic', $entity->getName());
        self::assertSame('test-clinic', $entity->getSlug());
        self::assertSame('Europe/Paris', $entity->getTimeZone());
        self::assertSame('fr-FR', $entity->getLocale());
        self::assertSame(ClinicStatus::ACTIVE, $entity->getStatus());
        self::assertNotNull($entity->getClinicGroupId());
        self::assertSame($clinic->clinicGroupId()->toString(), $entity->getClinicGroupId()->toString());
    }

    public function testToEntityWithoutClinicGroup(): void
    {
        $clinic = Clinic::create(
            id: ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            clinicGroupId: null,
        );

        $entity = $this->mapper->toEntity($clinic);

        self::assertNull($entity->getClinicGroupId());
    }

    public function testRoundTrip(): void
    {
        $entity = new ClinicEntity();
        $entity->setId(Uuid::v7());
        $entity->setName('Test Clinic');
        $entity->setSlug('test-clinic');
        $entity->setTimeZone('America/New_York');
        $entity->setLocale('en-US');
        $entity->setStatus(ClinicStatus::SUSPENDED);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01T10:00:00Z'));
        $entity->setUpdatedAt(new \DateTimeImmutable('2024-01-02T10:00:00Z'));
        $entity->setClinicGroupId(Uuid::v7());

        $domain = $this->mapper->toDomain($entity);
        $entityBack = $this->mapper->toEntity($domain);

        self::assertSame($entity->getId()->toString(), $entityBack->getId()->toString());
        self::assertSame($entity->getName(), $entityBack->getName());
        self::assertSame($entity->getSlug(), $entityBack->getSlug());
        self::assertSame($entity->getTimeZone(), $entityBack->getTimeZone());
        self::assertSame($entity->getLocale(), $entityBack->getLocale());
        self::assertSame($entity->getStatus(), $entityBack->getStatus());
        self::assertSame($entity->getClinicGroupId()->toString(), $entityBack->getClinicGroupId()->toString());
    }
}
