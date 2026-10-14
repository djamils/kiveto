<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Pricing;

use App\Context\Catalog\Application\Command\Pricing\CreatePriceList\CreatePriceList;
use App\Context\Catalog\Application\Command\Pricing\CreatePriceList\CreatePriceListHandler;
use App\Context\Catalog\Domain\Pricing\Event\PriceListCreated;
use App\Context\Catalog\Domain\Pricing\Exception\DefaultPriceListAlreadyExistsException;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePriceListHandler::class)]
#[CoversClass(CreatePriceList::class)]
final class CreatePriceListHandlerTest extends TestCase
{
    public function testTheFirstDefaultPriceListOfAClinicIsCreated(): void
    {
        $saved      = null;
        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('hasDefaultForClinic')->willReturn(false);
        $repository->expects(self::once())->method('save')
            ->willReturnCallback(static function (PriceList $priceList) use (&$saved): void {
                $saved = $priceList;
            })
        ;

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PriceListCreated::class))
        ;

        $id = $this->handler($repository, $eventBus)(new CreatePriceList(F::CLINIC_ID, 'Tarif standard', true));

        self::assertSame(F::GENERATED_ID, $id);
        self::assertInstanceOf(PriceList::class, $saved);
        self::assertTrue($saved->isDefault());
        self::assertSame('Tarif standard', $saved->name()->toString());
    }

    public function testANonDefaultPriceListDoesNotCheckForAnExistingDefault(): void
    {
        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->expects(self::never())->method('hasDefaultForClinic');
        $repository->expects(self::once())->method('save');

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new CreatePriceList(F::CLINIC_ID, 'Tarif éleveurs', false),
        );
    }

    public function testASecondDefaultPriceListIsRefused(): void
    {
        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('hasDefaultForClinic')->willReturn(true);
        $repository->expects(self::never())->method('save');

        $this->expectException(DefaultPriceListAlreadyExistsException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new CreatePriceList(F::CLINIC_ID, 'Tarif standard', true),
        );
    }

    private function handler(
        PriceListRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): CreatePriceListHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(F::GENERATED_ID);

        return new CreatePriceListHandler(
            $repository,
            $uuidGenerator,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
