<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\ListSupportedRegimes;

use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\TaxRegime;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListSupportedRegimesHandler
{
    public function __construct(private TaxRegimeRepositoryInterface $regimeRepo)
    {
    }

    /** @return list<TaxRegime> */
    public function __invoke(ListSupportedRegimes $query): array
    {
        return $this->regimeRepo->findAllActive();
    }
}
