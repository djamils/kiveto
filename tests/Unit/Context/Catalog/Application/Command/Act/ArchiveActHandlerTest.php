<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Act;

use App\Context\Catalog\Application\Command\Act\ArchiveAct\ArchiveAct;
use App\Context\Catalog\Application\Command\Act\ArchiveAct\ArchiveActHandler;
use App\Context\Catalog\Domain\Act\Event\ActArchived;
use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveActHandler::class)]
#[CoversClass(ArchiveAct::class)]
final class ArchiveActHandlerTest extends TestCase
{
    public function testAnActiveActIsArchivedSavedAndAnnounced(): void
    {
        $act = F::act();

        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn($act);
        $repository->expects(self::once())->method('save')->with($act);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ActArchived::class))
        ;

        $this->handler($repository, $eventBus)(new ArchiveAct(F::ACT_ID, F::CLINIC_ID));

        self::assertSame(ActStatus::ARCHIVED, $act->status());
        self::assertSame(F::now(), $act->updatedAt());
    }

    public function testAnUnknownActIsRefused(): void
    {
        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ActNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new ArchiveAct(F::ACT_ID, F::CLINIC_ID),
        );
    }

    private function handler(ActRepositoryInterface $repository, EventBusInterface $eventBus): ArchiveActHandler
    {
        return new ArchiveActHandler($repository, new FrozenClock(F::now()), new DomainEventPublisher($eventBus));
    }
}
