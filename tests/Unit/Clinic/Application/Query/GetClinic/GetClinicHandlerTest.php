<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Query\GetClinic;

use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Clinic\Application\Query\GetClinic\GetClinicHandler;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use PHPUnit\Framework\TestCase;

final class GetClinicHandlerTest extends TestCase
{
    public function testReturnsClinicDto(): void
    {
        $clinic = Clinic::create(
            id: ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
            clinicGroupId: ClinicGroupId::fromString('018f1b1e-9999-7890-abcd-0123456789ab'),
        );

        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with(self::callback(static function ($id): bool {
                \assert($id instanceof ClinicId);

                return '018f1b1e-1234-7890-abcd-0123456789ab' === $id->toString();
            }))
            ->willReturn($clinic)
        ;

        $handler = new GetClinicHandler($repo);
        $dto     = $handler(new GetClinic('018f1b1e-1234-7890-abcd-0123456789ab'));

        self::assertNotNull($dto);
        self::assertSame('018f1b1e-1234-7890-abcd-0123456789ab', $dto->id);
        self::assertSame('Test Clinic', $dto->name);
        self::assertSame('test-clinic', $dto->slug);
        self::assertSame('Europe/Paris', $dto->timeZone);
        self::assertSame('fr-FR', $dto->locale);
        self::assertSame(ClinicStatus::ACTIVE, $dto->status);
        self::assertSame('018f1b1e-9999-7890-abcd-0123456789ab', $dto->clinicGroupId);
        self::assertSame('2024-01-01T10:00:00+00:00', $dto->createdAt);
    }

    public function testReturnsNullWhenClinicNotFound(): void
    {
        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $handler = new GetClinicHandler($repo);
        $dto     = $handler(new GetClinic('018f1b1e-1234-7890-abcd-0123456789ab'));

        self::assertNull($dto);
    }
}
