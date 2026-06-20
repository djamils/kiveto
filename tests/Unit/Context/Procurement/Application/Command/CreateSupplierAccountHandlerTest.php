<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\CreateSupplierAccount\CreateSupplierAccount;
use App\Context\Procurement\Application\Command\CreateSupplierAccount\CreateSupplierAccountHandler;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Exception\DuplicateSupplierAccountException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CreateSupplierAccountHandlerTest extends TestCase
{
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000010';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItCreatesAccount(): void
    {
        $repository = $this->createMock(SupplierAccountRepositoryInterface::class);
        $repository->method('findByClinicAndSupplier')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->makeHandler($repository, $eventBus)(new CreateSupplierAccount(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            customerCode: 'CLI-001',
            notes: 'Some notes',
        ));
    }

    public function testItThrowsOnDuplicate(): void
    {
        $existing = SupplierAccount::create(
            id: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            customerCode: CustomerCode::fromString('CLI-001'),
        );

        $repository = $this->createStub(SupplierAccountRepositoryInterface::class);
        $repository->method('findByClinicAndSupplier')->willReturn($existing);

        $this->expectException(DuplicateSupplierAccountException::class);

        $this->makeHandler($repository, $this->createStub(EventBusInterface::class))(new CreateSupplierAccount(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
            customerCode: 'CLI-001',
        ));
    }

    private function makeHandler(
        SupplierAccountRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): CreateSupplierAccountHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::ACCOUNT_UUID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static function (callable $fn): void {
                $fn();
            },
        );

        return new CreateSupplierAccountHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
        );
    }
}
