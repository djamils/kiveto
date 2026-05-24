<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle;

use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Exception\DuplicateArticleCodeException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateGtinException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\Service\UnitOfMeasureRegistry;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateNonDrugArticleHandler
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly TaxCategoryRegistryInterface $taxCategoryRegistry,
        private readonly ClinicInfoProviderInterface $clinicInfoProvider,
        private readonly UnitOfMeasureRegistry $unitOfMeasureRegistry,
    ) {
    }

    public function __invoke(CreateNonDrugArticle $command): string
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $taxCategory = TaxCategoryCode::fromString($command->taxCategoryCode);

        if (!$this->taxCategoryRegistry->has($taxCategory)) {
            throw new \DomainException(\sprintf('Unknown tax category code: "%s".', $command->taxCategoryCode));
        }

        $clinicCurrency = $this->clinicInfoProvider->getCurrencyCode($clinicId);

        if ($clinicCurrency->toString() !== $command->basePriceCurrency) {
            throw new ClinicCurrencyMismatchException($clinicCurrency->toString(), $command->basePriceCurrency);
        }

        $unitOfMeasure = UnitOfMeasure::fromString($command->unitOfMeasure);
        $this->unitOfMeasureRegistry->resolve($unitOfMeasure);

        $code = ArticleCode::fromString($command->code);

        if ($this->articleRepository->existsByCode($code, $clinicId)) {
            throw new DuplicateArticleCodeException($command->code);
        }

        $gtin = null !== $command->gtin ? Gtin::fromString($command->gtin) : null;

        if (null !== $gtin && $this->articleRepository->existsByGtin($gtin, $clinicId)) {
            throw new DuplicateGtinException($command->gtin ?? '');
        }

        $articleId = ArticleId::fromString($this->uuidGenerator->generate());
        $now       = $this->clock->now();

        $article = Article::createNonDrug(
            id: $articleId,
            clinicId: $clinicId,
            name: ArticleName::fromString($command->name),
            code: $code,
            kind: ArticleKind::from($command->kind),
            gtin: $gtin,
            taxCategory: $taxCategory,
            basePrice: Money::fromMinorUnits(
                $command->basePriceMinorUnits,
                CurrencyCode::fromString($command->basePriceCurrency),
            ),
            unitOfMeasure: $unitOfMeasure,
            trackStock: $command->trackStock,
            createdAt: $now,
        );

        $this->articleRepository->save($article);
        $this->domainEventPublisher->publish($article);

        return $articleId->toString();
    }
}
