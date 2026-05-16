<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Service;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\Service\MoneyCalculator;
use App\Shared\Money\Domain\Service\RoundingPolicyRegistry;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Exception\AmbiguousRateException;
use App\System\Taxation\Domain\Exception\NoApplicableRateException;
use App\System\Taxation\Domain\Exception\UnknownTaxCategoryException;
use App\System\Taxation\Domain\Exception\UnknownTaxRegimeException;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\ValueObject\AppliedTaxRate;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxableItem;
use App\System\Taxation\Domain\ValueObject\TaxApplication;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final readonly class TaxResolver
{
    public function __construct(
        private TaxRegimeRepositoryInterface $regimeRepo,
        private TaxCategoryRegistryInterface $categoryRegistry,
        private MoneyCalculator $calculator,
        private RoundingPolicyRegistry $roundingRegistry,
        private MentionTemplateResolver $mentionResolver,
        private ClockInterface $clock,
    ) {
    }

    public function resolve(TaxableItem $item, TaxRegimeId $regimeId, FiscalContext $context): TaxApplication
    {
        $regime = $this->regimeRepo->findById($regimeId);

        if (null === $regime || !$regime->isActive()) {
            throw new UnknownTaxRegimeException($regimeId->toString());
        }

        if (!$this->categoryRegistry->has($item->category())) {
            throw new UnknownTaxCategoryException($item->category()->toString());
        }

        $candidates = $regime->findCandidateRates($item->category(), $context);

        if (0 === \count($candidates)) {
            throw new NoApplicableRateException(
                $item->category()->toString(),
                $regimeId->toString(),
                $context->saleDate(),
            );
        }

        if (1 === \count($candidates)) {
            $winner = $candidates[0];
        } else {
            $scored = array_map(
                fn (TaxRate $r): array => [$r, $r->appliesTo()->specificityScore($item->category(), $context)],
                $candidates,
            );

            usort($scored, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

            if ($scored[0][1] === $scored[1][1]) {
                throw new AmbiguousRateException(
                    $item->category()->toString(),
                    $regimeId->toString(),
                    [$scored[0][0]->id()->toString(), $scored[1][0]->id()->toString()],
                );
            }

            $winner = $scored[0][0];
        }

        $roundingPolicy = $this->roundingRegistry->accounting();
        /** @var numeric-string $factor */
        $factor      = $winner->value()->toBcmathFactor();
        $taxAmount   = $this->calculator->applyCoefficient($item->netAmount(), $factor, $roundingPolicy);
        $grossAmount = $this->calculator->add($item->netAmount(), $taxAmount);

        $appliedRate = AppliedTaxRate::of(
            $winner->id(),
            $regimeId,
            $winner->value(),
            $winner->validityPeriod()->validFrom(),
            'yaml.' . $winner->id()->toString(),
        );

        $mentions = $this->mentionResolver->findApplicable($regimeId, $context, $appliedRate);

        return TaxApplication::of(
            $regimeId,
            $item->category(),
            $appliedRate,
            $item->netAmount(),
            $taxAmount,
            $grossAmount,
            RoundingPolicyId::ACCOUNTING,
            $mentions,
            $context,
            $this->clock->now(),
        );
    }
}
