<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Registry;

use App\System\Taxation\Domain\Exception\UnknownTaxCategoryException;
use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;

final readonly class DoctrineTaxCategoryRegistry implements TaxCategoryRegistryInterface
{
    public function __construct(private TaxCategoryRepositoryInterface $repo)
    {
    }

    public function get(TaxCategoryCode $code): TaxCategory
    {
        $category = $this->repo->findByCode($code);

        if (null === $category) {
            throw new UnknownTaxCategoryException($code->toString());
        }

        return $category;
    }

    /** @return list<TaxCategory> */
    public function listActive(): array
    {
        return $this->repo->findAllActive();
    }

    public function has(TaxCategoryCode $code): bool
    {
        return null !== $this->repo->findByCode($code);
    }

    public function natureOf(TaxCategoryCode $code): ?TaxCategoryNature
    {
        return $this->repo->findByCode($code)?->nature();
    }
}
