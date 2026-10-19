<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * The dated check of a French business profile against the company registry
 * and VIES, plus the time a new check was last requested (null when none is
 * awaited). Advisory only: nothing here ever blocks the clinic.
 */
final class TaxProfileVerification
{
    // A request younger than this is still awaited: no second one is queued.
    private const string PENDING_WINDOW = 'PT24H';

    private CompanyCheck $company;
    private VatCheck $vat;
    private ?\DateTimeImmutable $requestedAt;

    private function __construct(CompanyCheck $company, VatCheck $vat, ?\DateTimeImmutable $requestedAt)
    {
        $this->company     = $company;
        $this->vat         = $vat;
        $this->requestedAt = $requestedAt;
    }

    public static function unchecked(?\DateTimeImmutable $requestedAt = null): self
    {
        return new self(CompanyCheck::unchecked(), VatCheck::unchecked(), $requestedAt);
    }

    public static function of(CompanyCheck $company, VatCheck $vat, ?\DateTimeImmutable $requestedAt = null): self
    {
        return new self($company, $vat, $requestedAt);
    }

    public function company(): CompanyCheck
    {
        return $this->company;
    }

    public function vat(): VatCheck
    {
        return $this->vat;
    }

    public function requestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function withRequestedAt(?\DateTimeImmutable $requestedAt): self
    {
        return new self($this->company, $this->vat, $requestedAt);
    }

    /**
     * Requested less than 24 hours ago and not answered yet.
     */
    public function isPending(\DateTimeImmutable $now): bool
    {
        return null !== $this->requestedAt
            && $this->requestedAt > $now->sub(new \DateInterval(self::PENDING_WINDOW));
    }

    /**
     * Due for a new check: never answered, an outage, or a company check $maxAge old or more.
     */
    public function isStale(\DateTimeImmutable $now, \DateInterval $maxAge): bool
    {
        if (!$this->company->outcome()->isDefinite() || !$this->vat->outcome()->isDefinite()) {
            return true;
        }

        $checkedAt = $this->company->checkedAt();

        return null === $checkedAt || $checkedAt <= $now->sub($maxAge);
    }

    /**
     * @return list<TaxProfileWarning> in display order
     */
    public function warnings(?string $enteredLegalName): array
    {
        $warnings = [];

        $warning = match ($this->company->outcome()) {
            CompanyCheckOutcome::CEASED       => TaxProfileWarning::COMPANY_CEASED,
            CompanyCheckOutcome::NOT_FOUND    => TaxProfileWarning::COMPANY_NOT_FOUND,
            CompanyCheckOutcome::CONFIDENTIAL => TaxProfileWarning::COMPANY_CONFIDENTIAL,
            CompanyCheckOutcome::UNCHECKED, CompanyCheckOutcome::ACTIVE, CompanyCheckOutcome::UNAVAILABLE => null,
        };

        if (null !== $warning) {
            $warnings[] = $warning;
        }

        if (VatCheckOutcome::INVALID === $this->vat->outcome()) {
            $warnings[] = TaxProfileWarning::VAT_INVALID;
        }

        if ($this->nameDiffers($enteredLegalName)) {
            $warnings[] = TaxProfileWarning::NAME_MISMATCH;
        }

        if (!$this->company->outcome()->isDefinite() || !$this->vat->outcome()->isDefinite()) {
            $warnings[] = TaxProfileWarning::UNVERIFIED;
        }

        return $warnings;
    }

    public function equals(self $other): bool
    {
        return $this->company->equals($other->company)
            && $this->vat->equals($other->vat)
            && $this->requestedAt?->getTimestamp() === $other->requestedAt?->getTimestamp();
    }

    private function nameDiffers(?string $enteredLegalName): bool
    {
        $registryName = $this->company->registryName();
        $comparable   = \in_array($this->company->outcome(), [CompanyCheckOutcome::ACTIVE, CompanyCheckOutcome::CEASED], true);

        if (!$comparable || null === $registryName || null === $enteredLegalName) {
            return false;
        }

        // An establishment sharing the SIREN (« Élevage des Calanques Aubagne ») is not a different name.
        $entered  = LegalNameNormalizer::normalize($enteredLegalName);
        $official = LegalNameNormalizer::normalize($registryName);

        return !str_contains($entered, $official) && !str_contains($official, $entered);
    }
}
