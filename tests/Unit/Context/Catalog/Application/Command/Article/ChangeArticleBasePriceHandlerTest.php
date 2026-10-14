<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\ChangeArticleBasePrice\ChangeArticleBasePrice;
use App\Context\Catalog\Application\Command\Article\ChangeArticleBasePrice\ChangeArticleBasePriceHandler;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Article\Event\ArticleBasePriceChanged;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeArticleBasePriceHandler::class)]
#[CoversClass(ChangeArticleBasePrice::class)]
final class ChangeArticleBasePriceHandlerTest extends TestCase
{
    public function testTheArticleTakesItsNewBasePrice(): void
    {
        $article = F::consumable();

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save')->with($article);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ArticleBasePriceChanged::class))
        ;

        $this->handler($repository, $eventBus)(
            new ChangeArticleBasePrice(F::ARTICLE_ID, F::CLINIC_ID, 420, 'EUR'),
        );

        self::assertSame(420, $article->basePrice()->minorUnits());
    }

    public function testAPriceInAnotherCurrencyThanTheClinicsIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::never())->method('save');

        $this->expectException(ClinicCurrencyMismatchException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new ChangeArticleBasePrice(F::ARTICLE_ID, F::CLINIC_ID, 420, 'CHF'),
        );
    }

    public function testAnUnknownArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArticleNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new ChangeArticleBasePrice(F::ARTICLE_ID, F::CLINIC_ID, 420, 'EUR'),
        );
    }

    private function handler(
        ArticleRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): ChangeArticleBasePriceHandler {
        $clinicInfo = $this->createStub(ClinicInfoProviderInterface::class);
        $clinicInfo->method('getCurrencyCode')->willReturn(CurrencyCode::fromString('EUR'));

        return new ChangeArticleBasePriceHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
            $clinicInfo,
        );
    }
}
