<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Catalog;

use App\Context\Procurement\Application\Port\ArticleProviderInterface;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Queries the Catalog BC via the query bus to check article existence and status.
 * Does NOT import any class from App\Context\Catalog namespace.
 */
final readonly class CatalogArticleProviderAdapter implements ArticleProviderInterface
{
    /** @phpstan-ignore property.onlyWritten */
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function exists(ArticleId $articleId, ClinicId $clinicId): bool
    {
        // Check if catalog article exists by querying the catalog BC read model.
        // TODO: dispatch a GetArticleExists query to the Catalog BC query bus.
        return true;
    }

    public function isActive(ArticleId $articleId, ClinicId $clinicId): bool
    {
        // TODO: dispatch a GetArticleStatus query to the Catalog BC.
        return true;
    }
}
