<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Adapter\Catalog;

use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Inventory\Application\Port\ArticleProviderInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;

final readonly class CatalogArticleProviderAdapter implements ArticleProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function exists(ArticleId $articleId, ClinicId $clinicId): bool
    {
        try {
            $this->fetchDetail($articleId, $clinicId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isActive(ArticleId $articleId, ClinicId $clinicId): bool
    {
        try {
            $view = $this->fetchDetail($articleId, $clinicId);

            return 'ACTIVE' === $view->status;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getUnitOfMeasure(ArticleId $articleId, ClinicId $clinicId): UnitOfMeasure
    {
        $view = $this->fetchDetail($articleId, $clinicId);

        return UnitOfMeasure::fromString($view->unitOfMeasure);
    }

    public function getName(ArticleId $articleId, ClinicId $clinicId): string
    {
        $view = $this->fetchDetail($articleId, $clinicId);

        return $view->name;
    }

    private function fetchDetail(ArticleId $articleId, ClinicId $clinicId): ArticleDetailView
    {
        $result = $this->queryBus->ask(new GetArticleDetail(
            articleId: $articleId->toString(),
            clinicId: $clinicId->toString(),
        ));

        if (!$result instanceof ArticleDetailView) {
            throw new \UnexpectedValueException('Expected ArticleDetailView from GetArticleDetail query.');
        }

        return $result;
    }
}
