<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Article\GetArticleDetail;

use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetArticleDetailHandler
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
    ) {
    }

    public function __invoke(GetArticleDetail $query): ArticleDetailView
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $article  = $this->articleRepository->findById(ArticleId::fromString($query->articleId), $clinicId);

        if (null === $article) {
            throw new ArticleNotFoundException($query->articleId);
        }

        $drugProps = $article->drugProperties();

        return new ArticleDetailView(
            id: $article->id()->toString(),
            clinicId: $article->clinicId()->toString(),
            name: $article->name()->toString(),
            code: $article->code()->toString(),
            kind: $article->kind()->value,
            gtin: $article->gtin()?->toString(),
            taxCategoryCode: $article->taxCategory()->toString(),
            basePriceMinorUnits: $article->basePrice()->minorUnits(),
            basePriceCurrency: $article->basePrice()->currency()->toString(),
            unitOfMeasure: $article->unitOfMeasure()->toString(),
            trackStock: $article->trackStock(),
            status: $article->status()->value,
            authorizationRef: $drugProps?->authorizationRef()?->toString(),
            requiresPrescription: $drugProps?->requiresPrescription(),
            prescriptionClass: $drugProps?->prescriptionClass()?->value,
            isControlledSubstance: $drugProps?->isControlledSubstance(),
            createdAt: $article->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $article->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
