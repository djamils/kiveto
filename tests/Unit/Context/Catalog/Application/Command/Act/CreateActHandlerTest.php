<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Act;

use App\Context\Catalog\Application\Command\Act\CreateAct\CreateAct;
use App\Context\Catalog\Application\Command\Act\CreateAct\CreateActHandler;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Act\Exception\DuplicateActCodeException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class CreateActHandlerTest extends TestCase
{
    private UuidGeneratorInterface&Stub $uuidGenerator;
    private ClockInterface&Stub $clock;
    private TaxCategoryRegistryInterface&Stub $taxRegistry;
    private ClinicInfoProviderInterface&Stub $clinicInfoProvider;

    protected function setUp(): void
    {
        $this->uuidGenerator      = $this->createStub(UuidGeneratorInterface::class);
        $this->clock              = $this->createStub(ClockInterface::class);
        $this->taxRegistry        = $this->createStub(TaxCategoryRegistryInterface::class);
        $this->clinicInfoProvider = $this->createStub(ClinicInfoProviderInterface::class);

        $this->uuidGenerator->method('generate')->willReturn('01950000-0000-7000-0000-000000000001');
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $this->taxRegistry->method('has')->willReturn(true);
        $this->clinicInfoProvider->method('getCurrencyCode')->willReturn(CurrencyCode::fromString('EUR'));
    }

    public function testCreatesAct(): void
    {
        /** @var ActRepositoryInterface&MockObject $actRepository */
        $actRepository = $this->createMock(ActRepositoryInterface::class);
        $actRepository->method('existsByCode')->willReturn(false);
        $actRepository->expects(self::once())->method('save');

        $result = ($this->makeHandler($actRepository))(new CreateAct(
            clinicId: '01950000-0000-7000-0000-000000000002',
            name: 'Consultation standard',
            code: 'CONS-STD',
            description: null,
            category: 'CONSULTATION',
            taxCategoryCode: 'veterinary.act.consultation',
            basePriceMinorUnits: 5000,
            basePriceCurrency: 'EUR',
            estimatedDurationMinutes: 20,
            requiresAnesthesia: false,
        ));

        self::assertSame('01950000-0000-7000-0000-000000000001', $result);
    }

    public function testThrowsOnCurrencyMismatch(): void
    {
        $actRepository = $this->createStub(ActRepositoryInterface::class);

        $this->expectException(ClinicCurrencyMismatchException::class);

        ($this->makeHandler($actRepository))(new CreateAct(
            clinicId: '01950000-0000-7000-0000-000000000002',
            name: 'Test',
            code: 'TEST',
            description: null,
            category: 'CONSULTATION',
            taxCategoryCode: 'veterinary.act.consultation',
            basePriceMinorUnits: 5000,
            basePriceCurrency: 'USD',
            estimatedDurationMinutes: 20,
            requiresAnesthesia: false,
        ));
    }

    public function testThrowsOnDuplicateCode(): void
    {
        $actRepository = $this->createStub(ActRepositoryInterface::class);
        $actRepository->method('existsByCode')->willReturn(true);

        $this->expectException(DuplicateActCodeException::class);

        ($this->makeHandler($actRepository))(new CreateAct(
            clinicId: '01950000-0000-7000-0000-000000000002',
            name: 'Test',
            code: 'CONS',
            description: null,
            category: 'CONSULTATION',
            taxCategoryCode: 'veterinary.act.consultation',
            basePriceMinorUnits: 5000,
            basePriceCurrency: 'EUR',
            estimatedDurationMinutes: 20,
            requiresAnesthesia: false,
        ));
    }

    private function makeHandler(ActRepositoryInterface $actRepository): CreateActHandler
    {
        return new CreateActHandler(
            actRepository: $actRepository,
            uuidGenerator: $this->uuidGenerator,
            clock: $this->clock,
            domainEventPublisher: new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            taxCategoryRegistry: $this->taxRegistry,
            clinicInfoProvider: $this->clinicInfoProvider,
        );
    }
}
