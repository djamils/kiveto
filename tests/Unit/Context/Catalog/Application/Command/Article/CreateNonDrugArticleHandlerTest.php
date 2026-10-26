<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle\CreateNonDrugArticle;
use App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle\CreateNonDrugArticleHandler;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Event\ArticleCreated;
use App\Context\Catalog\Domain\Article\Exception\DuplicateArticleCodeException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateGtinException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\UnitOfMeasure\Service\UnitOfMeasureRegistry;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitCategory;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitDefinition;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateNonDrugArticleHandler::class)]
#[CoversClass(CreateNonDrugArticle::class)]
final class CreateNonDrugArticleHandlerTest extends TestCase
{
    private bool $taxCategoryKnown = true;

    public function testAFoodArticleWithAGtinIsCreated(): void
    {
        $saved      = null;
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(false);
        $repository->method('existsByGtin')->willReturn(false);
        $repository->expects(self::once())->method('save')
            ->willReturnCallback(static function (Article $article) use (&$saved): void {
                $saved = $article;
            })
        ;

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ArticleCreated::class))
        ;

        $id = $this->handler($repository, $eventBus)($this->command(gtin: '3760123456789'));

        self::assertSame(F::GENERATED_ID, $id);
        self::assertInstanceOf(Article::class, $saved);
        self::assertSame(ArticleKind::FOOD, $saved->kind());
        self::assertSame('3760123456789', $saved->gtin()?->toString());
        self::assertNull($saved->drugProperties());
        self::assertFalse($saved->trackStock());
    }

    public function testAnArticleWithoutGtinSkipsTheGtinCheck(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(false);
        $repository->expects(self::never())->method('existsByGtin');
        $repository->expects(self::once())->method('save');

        $this->handler($repository, $this->createStub(EventBusInterface::class))($this->command());
    }

    public function testAnUnknownTaxCategoryIsRefused(): void
    {
        $this->taxCategoryKnown = false;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown tax category code: "veterinary.food".');

        $this->handler($this->repositoryNeverSaving(), $this->createStub(EventBusInterface::class))(
            $this->command(),
        );
    }

    public function testAPriceInAnotherCurrencyThanTheClinicsIsRefused(): void
    {
        $this->expectException(ClinicCurrencyMismatchException::class);

        $this->handler($this->repositoryNeverSaving(), $this->createStub(EventBusInterface::class))(
            $this->command(currency: 'GBP'),
        );
    }

    public function testADuplicateCodeIsRefused(): void
    {
        $repository = $this->repositoryNeverSaving();
        $repository->method('existsByCode')->willReturn(true);

        $this->expectException(DuplicateArticleCodeException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))($this->command());
    }

    public function testADuplicateGtinIsRefused(): void
    {
        $repository = $this->repositoryNeverSaving();
        $repository->method('existsByCode')->willReturn(false);
        $repository->method('existsByGtin')->willReturn(true);

        $this->expectException(DuplicateGtinException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            $this->command(gtin: '3760123456789'),
        );
    }

    private function command(?string $gtin = null, string $currency = 'EUR'): CreateNonDrugArticle
    {
        return new CreateNonDrugArticle(
            clinicId: F::CLINIC_ID,
            name: 'Croquettes chaton 2 kg',
            code: 'CROQ-CHAT-2',
            kind: 'FOOD',
            gtin: $gtin,
            taxCategoryCode: 'veterinary.food',
            basePriceMinorUnits: 2490,
            basePriceCurrency: $currency,
            basePriceMode: 'incl',
            unitOfMeasure: 'UNIT',
            trackStock: false,
        );
    }

    private function repositoryNeverSaving(): ArticleRepositoryInterface&MockObject
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        return $repository;
    }

    private function handler(
        ArticleRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): CreateNonDrugArticleHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(F::GENERATED_ID);

        $taxRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $taxRegistry->method('has')->willReturn($this->taxCategoryKnown);

        $clinicInfo = $this->createStub(ClinicInfoProviderInterface::class);
        $clinicInfo->method('getCurrencyCode')->willReturn(CurrencyCode::fromString('EUR'));

        $unitRegistry = $this->createStub(UnitOfMeasureRegistry::class);
        $unitRegistry->method('resolve')->willReturn(
            new UnitDefinition(UnitOfMeasure::fromString('UNIT'), UnitCategory::COUNT, null),
        );

        return new CreateNonDrugArticleHandler(
            articleRepository: $repository,
            uuidGenerator: $uuidGenerator,
            clock: new FrozenClock(F::now()),
            domainEventPublisher: new DomainEventPublisher($eventBus),
            taxCategoryRegistry: $taxRegistry,
            clinicInfoProvider: $clinicInfo,
            unitOfMeasureRegistry: $unitRegistry,
        );
    }
}
