<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\EnableSupplierAccount\EnableSupplierAccount;
use App\Context\Procurement\Application\Command\EnableSupplierAccount\EnableSupplierAccountHandler;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Exception\SupplierAccountNotFoundException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class EnableSupplierAccountHandlerTest extends TestCase
{
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000010';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItEnablesAccount(): void
    {
        $account = SupplierAccount::create(
            id: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            customerCode: CustomerCode::fromString('CLI-001'),
        );
        // Disable it first so we can re-enable
        $account->disable(new \DateTimeImmutable('2024-01-01'));
        $_ = $account->pullDomainEvents();

        $repository = $this->createMock(SupplierAccountRepositoryInterface::class);
        $repository->method('findById')->willReturn($account);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->makeHandler($repository, $eventBus)(new EnableSupplierAccount(accountId: self::ACCOUNT_UUID));

        self::assertSame(SupplierAccountStatus::ACTIVE, $account->status());
    }

    public function testItThrowsWhenAccountNotFound(): void
    {
        $repository = $this->createStub(SupplierAccountRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(SupplierAccountNotFoundException::class);

        $this->makeHandler($repository, $this->createStub(EventBusInterface::class))(new EnableSupplierAccount(accountId: self::ACCOUNT_UUID));
    }

    private function makeHandler(
        SupplierAccountRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): EnableSupplierAccountHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static function (callable $fn): void {
                $fn();
            },
        );

        return new EnableSupplierAccountHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $clock,
            $em,
        );
    }
}
