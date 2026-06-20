<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\RegisterSupplier\RegisterSupplier;
use App\Context\Procurement\Application\Command\RegisterSupplier\RegisterSupplierHandler;
use App\Context\Procurement\Domain\Supplier\Exception\DuplicateSupplierCodeException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RegisterSupplierHandlerTest extends TestCase
{
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItRegistersANewSupplier(): void
    {
        $repository = $this->createMock(SupplierRepositoryInterface::class);
        $repository->method('findByCode')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->makeHandler($repository, $eventBus)(new RegisterSupplier(
            name: 'Centravet',
            code: 'CENTRAVET',
            type: SupplierType::CENTRALE->value,
            countryCode: 'FR',
            defaultCurrency: 'EUR',
            integrationMode: SupplierIntegrationMode::SIMULATION->value,
            adapterIdentifier: null,
        ));
    }

    public function testItThrowsOnDuplicateCode(): void
    {
        $existing = Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: SupplierName::fromString('Centravet'),
            code: SupplierCode::fromString('CENTRAVET'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: null,
        );

        $repository = $this->createStub(SupplierRepositoryInterface::class);
        $repository->method('findByCode')->willReturn($existing);

        $this->expectException(DuplicateSupplierCodeException::class);

        $this->makeHandler($repository, $this->createStub(EventBusInterface::class))(new RegisterSupplier(
            name: 'Duplicate',
            code: 'CENTRAVET',
            type: SupplierType::CENTRALE->value,
            countryCode: 'FR',
            defaultCurrency: 'EUR',
            integrationMode: SupplierIntegrationMode::SIMULATION->value,
            adapterIdentifier: null,
        ));
    }

    private function makeHandler(
        SupplierRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): RegisterSupplierHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::SUPPLIER_UUID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static function (callable $fn): void {
                $fn();
            },
        );

        return new RegisterSupplierHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
        );
    }
}
