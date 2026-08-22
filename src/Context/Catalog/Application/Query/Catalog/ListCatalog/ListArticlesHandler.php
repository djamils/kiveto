<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

use App\Context\Catalog\Application\Port\CatalogListReadRepositoryInterface;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListArticlesHandler
{
    public function __construct(private CatalogListReadRepositoryInterface $catalog)
    {
    }

    /**
     * @return CatalogPage<ArticleListRow>
     */
    public function __invoke(ListArticles $query): CatalogPage
    {
        $result = $this->catalog->articles(ClinicId::fromString($query->clinicId), $query->criteria);

        return new CatalogPage(
            items: $result['items'],
            total: $result['total'],
            page: $query->criteria->page,
            perPage: $query->criteria->perPage,
        );
    }
}
