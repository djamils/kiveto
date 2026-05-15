<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

final class MentionTemplateCondition
{
    /**
     * @param RegionCode[]        $regionsMatching
     * @param CustomerTaxStatus[] $customerStatusesMatching
     * @param AnimalUsage[]       $animalUsagesMatching
     */
    private function __construct(
        private readonly array $regionsMatching,
        private readonly array $customerStatusesMatching,
        private readonly array $animalUsagesMatching,
        private readonly ?ClinicLiabilityStatus $clinicLiability,
    ) {
    }

    /**
     * @param RegionCode[]        $regionsMatching
     * @param CustomerTaxStatus[] $customerStatusesMatching
     * @param AnimalUsage[]       $animalUsagesMatching
     */
    public static function of(
        array $regionsMatching,
        array $customerStatusesMatching,
        array $animalUsagesMatching,
        ?ClinicLiabilityStatus $clinicLiability,
    ): self {
        return new self($regionsMatching, $customerStatusesMatching, $animalUsagesMatching, $clinicLiability);
    }

    public function matches(FiscalContext $context): bool
    {
        if ([] !== $this->regionsMatching) {
            $region = $context->region();
            if (null === $region) {
                return false;
            }
            $found = false;
            foreach ($this->regionsMatching as $r) {
                if ($r->toString() === $region->toString()) {
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
}
