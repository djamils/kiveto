<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\ValueObject;

final class DrugProperties
{
    private function __construct(
        private ?MarketingAuthorizationRef $authorizationRef,
        private bool $requiresPrescription,
        private ?PrescriptionClass $prescriptionClass,
        private bool $isControlledSubstance,
    ) {
    }

    public static function create(
        ?MarketingAuthorizationRef $authorizationRef,
        bool $requiresPrescription,
        ?PrescriptionClass $prescriptionClass,
        bool $isControlledSubstance,
    ): self {
        return new self(
            authorizationRef: $authorizationRef,
            requiresPrescription: $requiresPrescription,
            prescriptionClass: $prescriptionClass,
            isControlledSubstance: $isControlledSubstance,
        );
    }

    public function withUpdatedFlags(
        bool $requiresPrescription,
        ?PrescriptionClass $prescriptionClass,
        bool $isControlledSubstance,
    ): self {
        return new self(
            authorizationRef: $this->authorizationRef,
            requiresPrescription: $requiresPrescription,
            prescriptionClass: $prescriptionClass,
            isControlledSubstance: $isControlledSubstance,
        );
    }

    public function authorizationRef(): ?MarketingAuthorizationRef
    {
        return $this->authorizationRef;
    }

    public function requiresPrescription(): bool
    {
        return $this->requiresPrescription;
    }

    public function prescriptionClass(): ?PrescriptionClass
    {
        return $this->prescriptionClass;
    }

    public function isControlledSubstance(): bool
    {
        return $this->isControlledSubstance;
    }
}
