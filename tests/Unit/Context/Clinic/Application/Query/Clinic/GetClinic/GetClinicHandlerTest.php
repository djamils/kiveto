<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinicHandler;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
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
            countryCode: CountryCode::fromString('FR'),
            currencyCode: CurrencyCode::fromString('EUR'),
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
