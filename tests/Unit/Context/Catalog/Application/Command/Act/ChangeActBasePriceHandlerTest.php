<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Act;

use App\Context\Catalog\Application\Command\Act\ChangeActBasePrice\ChangeActBasePrice;
use App\Context\Catalog\Application\Command\Act\ChangeActBasePrice\ChangeActBasePriceHandler;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Act\Event\ActBasePriceChanged;
use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeActBasePriceHandler::class)]
#[CoversClass(ChangeActBasePrice::class)]
final class ChangeActBasePriceHandlerTest extends TestCase
{
    public function testTheActTakesItsNewBasePrice(): void
    {
        $act = F::act();

        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn($act);
        $repository->expects(self::once())->method('save')->with($act);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ActBasePriceChanged::class))
        ;

        $this->handler($repository, $eventBus)(new ChangeActBasePrice(F::ACT_ID, F::CLINIC_ID, 6500, 'EUR', 'incl'));

        self::assertSame(6500, $act->basePrice()->minorUnits());
        self::assertSame('EUR', $act->basePrice()->currency()->toString());
    }

    public function testAPriceInAnotherCurrencyThanTheClinicsIsRefused(): void
    {
        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::never())->method('save');

        $this->expectException(ClinicCurrencyMismatchException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new ChangeActBasePrice(F::ACT_ID, F::CLINIC_ID, 6500, 'USD', 'incl'),
        );
    }

    public function testAnUnknownActIsRefused(): void
    {
        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ActNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new ChangeActBasePrice(F::ACT_ID, F::CLINIC_ID, 6500, 'EUR', 'incl'),
        );
    }

    private function handler(ActRepositoryInterface $repository, EventBusInterface $eventBus): ChangeActBasePriceHandler
    {
        $clinicInfo = $this->createStub(ClinicInfoProviderInterface::class);
        $clinicInfo->method('getCurrencyCode')->willReturn(CurrencyCode::fromString('EUR'));

        return new ChangeActBasePriceHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
            $clinicInfo,
        );
    }
}
