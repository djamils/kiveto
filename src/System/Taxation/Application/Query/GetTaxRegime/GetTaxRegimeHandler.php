<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\GetTaxRegime;

use App\System\Taxation\Domain\Exception\UnknownTaxRegimeException;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\TaxRegime;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTaxRegimeHandler
{
    public function __construct(private TaxRegimeRepositoryInterface $regimeRepo)
    {
    }

    public function __invoke(GetTaxRegime $query): TaxRegime
    {
        $regime = $this->regimeRepo->findById($query->regimeId);

        if (null === $regime) {
            throw new UnknownTaxRegimeException($query->regimeId->toString());
        }

        return $regime;
    }
}
