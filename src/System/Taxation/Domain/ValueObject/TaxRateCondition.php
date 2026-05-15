<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class TaxRateCondition
{
    /**
     * @param string[]            $categoriesMatching
     * @param RegionCode[]        $regionsMatching
     * @param CustomerTaxStatus[] $customerStatusesMatching
     * @param AnimalUsage[]       $animalUsagesMatching
     */
    private function __construct(
        private readonly array $categoriesMatching,
        private readonly array $regionsMatching,
        private readonly array $customerStatusesMatching,
        private readonly array $animalUsagesMatching,
        private readonly ?ClinicLiabilityStatus $clinicLiability,
    ) {
    }

    /**
     * @param string[]            $categoriesMatching
     * @param RegionCode[]        $regionsMatching
     * @param CustomerTaxStatus[] $customerStatusesMatching
     * @param AnimalUsage[]       $animalUsagesMatching
     */
    public static function of(
        array $categoriesMatching,
        array $regionsMatching,
        array $customerStatusesMatching,
        array $animalUsagesMatching,
        ?ClinicLiabilityStatus $clinicLiability,
    ): self {
        return new self(
            $categoriesMatching,
            $regionsMatching,
            $customerStatusesMatching,
            $animalUsagesMatching,
            $clinicLiability,
        );
    }

    public function matches(TaxCategoryCode $category, FiscalContext $context): bool
    {
        if ([] !== $this->categoriesMatching) {
            $matched = false;
            foreach ($this->categoriesMatching as $pattern) {
                if (fnmatch($pattern, $category->toString())) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        if ([] !== $this->regionsMatching) {
            $region = $context->region();
            if (null === $region) {
                return false;
            }
            $regionStr = $region->toString();
            $found     = false;
            foreach ($this->regionsMatching as $r) {
                if ($r->toString() === $regionStr) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        if ([] !== $this->customerStatusesMatching) {
            $status = $context->customerTaxStatus();
            if (null === $status) {
                return false;
            }
            if (!\in_array($status, $this->customerStatusesMatching, true)) {
                return false;
            }
        }

        if ([] !== $this->animalUsagesMatching) {
            $usage = $context->animalUsage();
            if (null === $usage) {
                return false;
            }
            if (!\in_array($usage, $this->animalUsagesMatching, true)) {
                return false;
            }
        }

        if (null !== $this->clinicLiability && $context->clinicLiability() !== $this->clinicLiability) {
            return false;
        }

        return true;
    }

    public function specificityScore(TaxCategoryCode $cat, FiscalContext $ctx): int
    {
        $score = 0;

        if ([] !== $this->categoriesMatching) {
            foreach ($this->categoriesMatching as $pattern) {
                if (fnmatch($pattern, $cat->toString())) {
                    if (!str_contains($pattern, '*')) {
                        $score += 100;
                    } else {
                        $segments    = explode('.', $pattern);
                        $nonWildcard = \count(array_filter($segments, static fn (string $s) => '*' !== $s));
                        $score += $nonWildcard * 10;
                    }
                    break;
                }
            }
        }

        if ([] !== $this->regionsMatching) {
            $score += 5;
        }

        if ([] !== $this->customerStatusesMatching) {
            $score += 4;
        }

        if ([] !== $this->animalUsagesMatching) {
            $score += 3;
        }

        if (null !== $this->clinicLiability) {
            $score += 2;
        }

        return $score;
    }

    /** @return string[] */
    public function categoriesMatching(): array
    {
        return $this->categoriesMatching;
    }

    /** @return RegionCode[] */
    public function regionsMatching(): array
    {
        return $this->regionsMatching;
    }

    /** @return CustomerTaxStatus[] */
    public function customerStatusesMatching(): array
    {
        return $this->customerStatusesMatching;
    }

    /** @return AnimalUsage[] */
    public function animalUsagesMatching(): array
    {
        return $this->animalUsagesMatching;
    }

    public function clinicLiability(): ?ClinicLiabilityStatus
    {
        return $this->clinicLiability;
    }

    public function equals(self $other): bool
    {
        $cats1 = $this->categoriesMatching;
        $cats2 = $other->categoriesMatching;
        sort($cats1);
        sort($cats2);
        if ($cats1 !== $cats2) {
            return false;
        }

        $regions1 = array_map(static fn (RegionCode $r) => $r->toString(), $this->regionsMatching);
        $regions2 = array_map(static fn (RegionCode $r) => $r->toString(), $other->regionsMatching);
        sort($regions1);
        sort($regions2);
        if ($regions1 !== $regions2) {
            return false;
        }

        $statuses1 = array_map(static fn (CustomerTaxStatus $s) => $s->value, $this->customerStatusesMatching);
        $statuses2 = array_map(static fn (CustomerTaxStatus $s) => $s->value, $other->customerStatusesMatching);
        sort($statuses1);
        sort($statuses2);
        if ($statuses1 !== $statuses2) {
            return false;
        }

        $usages1 = array_map(static fn (AnimalUsage $u) => $u->value, $this->animalUsagesMatching);
        $usages2 = array_map(static fn (AnimalUsage $u) => $u->value, $other->animalUsagesMatching);
        sort($usages1);
        sort($usages2);
        if ($usages1 !== $usages2) {
            return false;
        }

        return $this->clinicLiability === $other->clinicLiability;
    }
}
