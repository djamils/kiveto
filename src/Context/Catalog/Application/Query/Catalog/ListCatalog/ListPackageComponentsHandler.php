<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

use App\Context\Catalog\Application\Port\CatalogListReadRepositoryInterface;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPackageComponentsHandler
{
    public function __construct(private CatalogListReadRepositoryInterface $catalog)
    {
    }

    /**
     * @return list<PackageComponentRow>
     */
    public function __invoke(ListPackageComponents $query): array
    {
        return $this->catalog->packageComponents(
            ClinicId::fromString($query->clinicId),
            $query->packageId,
        );
    }
}
