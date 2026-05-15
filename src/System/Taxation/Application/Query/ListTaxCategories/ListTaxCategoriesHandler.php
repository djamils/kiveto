<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\ListTaxCategories;

use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\TaxCategory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListTaxCategoriesHandler
{
    public function __construct(private TaxCategoryRepositoryInterface $categoryRepo)
    {
    }

    /** @return list<TaxCategory> */
    public function __invoke(ListTaxCategories $query): array
    {
        $all = $this->categoryRepo->findAllActive();

        if (null === $query->prefix) {
            return $all;
        }

        return array_values(
            array_filter($all, fn (TaxCategory $c): bool => str_starts_with($c->code()->toString(), $query->prefix)),
        );
    }
}
