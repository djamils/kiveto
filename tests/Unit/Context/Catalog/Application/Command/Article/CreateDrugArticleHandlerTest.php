<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\CreateDrugArticle\CreateDrugArticle;
use App\Context\Catalog\Application\Command\Article\CreateDrugArticle\CreateDrugArticleHandler;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Application\Port\PharmaceuticalRefProviderInterface;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Event\ArticleCreated;
use App\Context\Catalog\Domain\Article\Exception\AuthorizationNotMarketableException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateArticleCodeException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateGtinException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Infrastructure\Adapter\HealthRegistry\PharmaceuticalRef;
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

#[CoversClass(CreateDrugArticleHandler::class)]
#[CoversClass(CreateDrugArticle::class)]
final class CreateDrugArticleHandlerTest extends TestCase
{
    private const string AUTHORIZATION = 'FR/V/1234567 8/2020';

    private bool $taxCategoryKnown                = true;
    private ?PharmaceuticalRef $pharmaceuticalRef = null;

    public function testAPrescriptionDrugWithAMarketableAuthorizationIsCreated(): void
    {
        $this->pharmaceuticalRef = F::pharmaceuticalRef(self::AUTHORIZATION, 'ACTIVE');

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

        $id = $this->handler($repository, $eventBus)($this->command(
            gtin: '03760123456789',
            authorizationRef: self::AUTHORIZATION,
            prescriptionClass: 'RX',
        ));

        self::assertSame(F::GENERATED_ID, $id);
        self::assertInstanceOf(Article::class, $saved);
        self::assertSame(ArticleKind::DRUG, $saved->kind());
        self::assertSame('03760123456789', $saved->gtin()?->toString());
        self::assertSame(1250, $saved->basePrice()->minorUnits());

        $properties = $saved->drugProperties();
        self::assertNotNull($properties);
        self::assertSame(self::AUTHORIZATION, $properties->authorizationRef()?->toString());
        self::assertTrue($properties->requiresPrescription());
        self::assertSame(PrescriptionClass::RX, $properties->prescriptionClass());
    }

    public function testAnOverTheCounterDrugWithoutAuthorizationOrGtinIsCreated(): void
    {
        $refProvider = $this->createMock(PharmaceuticalRefProviderInterface::class);
        $refProvider->expects(self::never())->method('findById');

        $saved      = null;
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(false);
        $repository->expects(self::never())->method('existsByGtin');
        $repository->expects(self::once())->method('save')
            ->willReturnCallback(static function (Article $article) use (&$saved): void {
                $saved = $article;
            })
        ;

        $this->handler($repository, $this->createStub(EventBusInterface::class), $refProvider)(
            $this->command(requiresPrescription: false),
        );

        self::assertInstanceOf(Article::class, $saved);
        self::assertNull($saved->gtin());

        $properties = $saved->drugProperties();
        self::assertNotNull($properties);
        self::assertNull($properties->authorizationRef());
        self::assertNull($properties->prescriptionClass());
    }

    public function testAnUnknownTaxCategoryIsRefused(): void
    {
        $this->taxCategoryKnown = false;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown tax category code: "veterinary.drug.prescription".');

        $this->handler($this->repositoryNeverSaving(), $this->createStub(EventBusInterface::class))(
            $this->command(),
        );
    }

    public function testAPriceInAnotherCurrencyThanTheClinicsIsRefused(): void
    {
        $this->expectException(ClinicCurrencyMismatchException::class);

        $this->handler($this->repositoryNeverSaving(), $this->createStub(EventBusInterface::class))(
            $this->command(currency: 'USD'),
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
            $this->command(gtin: '03760123456789'),
        );
    }

    public function testAnAuthorizationUnknownToTheRegistryIsRefused(): void
    {
        $this->pharmaceuticalRef = null;

        $repository = $this->repositoryNeverSaving();
        $repository->method('existsByCode')->willReturn(false);

        $this->expectException(AuthorizationNotMarketableException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            $this->command(authorizationRef: self::AUTHORIZATION),
        );
    }

    public function testAWithdrawnAuthorizationIsRefused(): void
    {
        $this->pharmaceuticalRef = F::pharmaceuticalRef(self::AUTHORIZATION, 'WITHDRAWN');

        $repository = $this->repositoryNeverSaving();
        $repository->method('existsByCode')->willReturn(false);

        $this->expectException(AuthorizationNotMarketableException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            $this->command(authorizationRef: self::AUTHORIZATION),
        );
    }

    private function command(
        ?string $gtin = null,
        string $currency = 'EUR',
        ?string $authorizationRef = null,
        bool $requiresPrescription = true,
        ?string $prescriptionClass = null,
    ): CreateDrugArticle {
        return new CreateDrugArticle(
            clinicId: F::CLINIC_ID,
            name: 'Amoxicilline 250',
            code: 'AMOX-250',
            gtin: $gtin,
            taxCategoryCode: 'veterinary.drug.prescription',
            basePriceMinorUnits: 1250,
            basePriceCurrency: $currency,
            basePriceMode: 'incl',
            unitOfMeasure: 'UNIT',
            authorizationRef: $authorizationRef,
            requiresPrescription: $requiresPrescription,
            prescriptionClass: $prescriptionClass,
            isControlledSubstance: false,
            trackStock: true,
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
        ?PharmaceuticalRefProviderInterface $refProvider = null,
    ): CreateDrugArticleHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(F::GENERATED_ID);

        $taxRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $taxRegistry->method('has')->willReturn($this->taxCategoryKnown);

        $clinicInfo = $this->createStub(ClinicInfoProviderInterface::class);
        $clinicInfo->method('getCurrencyCode')->willReturn(CurrencyCode::fromString('EUR'));

        if (null === $refProvider) {
            $refProvider = $this->createStub(PharmaceuticalRefProviderInterface::class);
            $refProvider->method('findById')->willReturn($this->pharmaceuticalRef);
        }

        $unitRegistry = $this->createStub(UnitOfMeasureRegistry::class);
        $unitRegistry->method('resolve')->willReturn(
            new UnitDefinition(UnitOfMeasure::fromString('UNIT'), UnitCategory::COUNT, null),
        );

        return new CreateDrugArticleHandler(
            articleRepository: $repository,
            uuidGenerator: $uuidGenerator,
            clock: new FrozenClock(F::now()),
            domainEventPublisher: new DomainEventPublisher($eventBus),
            taxCategoryRegistry: $taxRegistry,
            clinicInfoProvider: $clinicInfo,
            pharmaceuticalRefProvider: $refProvider,
            unitOfMeasureRegistry: $unitRegistry,
        );
    }
}
