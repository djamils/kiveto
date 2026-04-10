<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ActiveClinicResultType;
use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ResolveActiveClinic;
use App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic\ResolveActiveClinicHandler;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Shared\Application\Bus\QueryBusInterface;
use PHPUnit\Framework\TestCase;

final class ResolveActiveClinicHandlerTest extends TestCase
{
    public function testReturnsNoneWhenNoClinics(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([]);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-uuid'));

        self::assertSame(ActiveClinicResultType::NONE, $result->type);
        self::assertNull($result->clinic);
    }

    public function testReturnsSingleWhenOneClinic(): void
    {
        $clinic = $this->buildClinic('clinic-1');

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([$clinic]);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-uuid'));

        self::assertSame(ActiveClinicResultType::SINGLE, $result->type);
        self::assertSame($clinic, $result->clinic);
    }

    public function testReturnsMultipleWhenManyClinics(): void
    {
        $clinics = [$this->buildClinic('clinic-1'), $this->buildClinic('clinic-2')];

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn($clinics);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-uuid'));

        self::assertSame(ActiveClinicResultType::MULTIPLE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(2, $result->clinics);
    }

    public function testReturnsDefaultClinicWhenMultipleClinicsAndOneIsDefault(): void
    {
        $clinic1 = $this->buildClinic('clinic-1');
        $clinic2 = $this->buildClinic('clinic-2', isDefault: true);
        $clinic3 = $this->buildClinic('clinic-3');

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([$clinic1, $clinic2, $clinic3]);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-uuid'));

        self::assertSame(ActiveClinicResultType::SINGLE, $result->type);
        self::assertSame($clinic2, $result->clinic);
    }

    public function testReturnsMultipleWhenMultipleClinicsAndNoDefault(): void
    {
        $clinics = [$this->buildClinic('clinic-1'), $this->buildClinic('clinic-2')];

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn($clinics);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-uuid'));

        self::assertSame(ActiveClinicResultType::MULTIPLE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(2, $result->clinics);
    }

    public function testReturnsMultipleWhenMultipleClinicsAndMultipleDefaults(): void
    {
        $clinics = [$this->buildClinic('clinic-1', isDefault: true), $this->buildClinic('clinic-2', isDefault: true)];

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn($clinics);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-uuid'));

        self::assertSame(ActiveClinicResultType::MULTIPLE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(2, $result->clinics);
    }

    private function buildClinic(string $id, bool $isDefault = false): AccessibleClinic
    {
        return new AccessibleClinic(
            clinicId: $id,
            clinicName: 'Clinic',
            clinicSlug: 'clinic',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            isDefault: $isDefault,
        );
    }
}
