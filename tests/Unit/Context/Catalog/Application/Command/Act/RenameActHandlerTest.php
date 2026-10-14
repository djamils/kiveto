<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Act;

use App\Context\Catalog\Application\Command\Act\RenameAct\RenameAct;
use App\Context\Catalog\Application\Command\Act\RenameAct\RenameActHandler;
use App\Context\Catalog\Domain\Act\Event\ActRenamed;
use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenameActHandler::class)]
#[CoversClass(RenameAct::class)]
final class RenameActHandlerTest extends TestCase
{
    public function testTheActTakesItsNewName(): void
    {
        $act = F::act();

        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn($act);
        $repository->expects(self::once())->method('save')->with($act);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ActRenamed::class))
        ;

        $this->handler($repository, $eventBus)(new RenameAct(F::ACT_ID, F::CLINIC_ID, 'Consultation de suivi'));

        self::assertSame('Consultation de suivi', $act->name()->toString());
    }

    public function testAnUnknownActIsRefused(): void
    {
        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ActNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new RenameAct(F::ACT_ID, F::CLINIC_ID, 'Consultation de suivi'),
        );
    }

    private function handler(ActRepositoryInterface $repository, EventBusInterface $eventBus): RenameActHandler
    {
        return new RenameActHandler($repository, new FrozenClock(F::now()), new DomainEventPublisher($eventBus));
    }
}
