<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The buyer's tax identity and structured address, frozen on an invoice or a
 * credit note row; every column is null for a private client.
 */
trait PayerFiscalColumns
{
    #[ORM\Column(name: 'payer_tax_status', type: 'string', length: 20, nullable: true)]
    private ?string $payerTaxStatus = null;

    #[ORM\Column(name: 'payer_legal_name', type: 'string', length: 255, nullable: true)]
    private ?string $payerLegalName = null;

    #[ORM\Column(name: 'payer_siren', type: 'string', length: 9, nullable: true)]
    private ?string $payerSiren = null;

    #[ORM\Column(name: 'payer_vat_number', type: 'string', length: 13, nullable: true)]
    private ?string $payerVatNumber = null;

    #[ORM\Column(name: 'payer_electronic_address_scheme', type: 'string', length: 8, nullable: true)]
    private ?string $payerElectronicAddressScheme = null;

    #[ORM\Column(name: 'payer_electronic_address', type: 'string', length: 64, nullable: true)]
    private ?string $payerElectronicAddress = null;

    /** @var list<string>|null */
    #[ORM\Column(name: 'payer_street', type: 'json', nullable: true)]
    private ?array $payerStreet = null;

    #[ORM\Column(name: 'payer_postal_code', type: 'string', length: 20, nullable: true)]
    private ?string $payerPostalCode = null;

    #[ORM\Column(name: 'payer_city', type: 'string', length: 255, nullable: true)]
    private ?string $payerCity = null;

    #[ORM\Column(name: 'payer_country_code', type: 'string', length: 2, nullable: true)]
    private ?string $payerCountryCode = null;

    public function getPayerTaxStatus(): ?string
    {
        return $this->payerTaxStatus;
    }

    public function getPayerLegalName(): ?string
    {
        return $this->payerLegalName;
    }

    public function getPayerSiren(): ?string
    {
        return $this->payerSiren;
    }

    public function getPayerVatNumber(): ?string
    {
        return $this->payerVatNumber;
    }

    public function getPayerElectronicAddressScheme(): ?string
    {
        return $this->payerElectronicAddressScheme;
    }

    public function getPayerElectronicAddress(): ?string
    {
        return $this->payerElectronicAddress;
    }

    /**
     * @return list<string>|null
     */
    public function getPayerStreet(): ?array
    {
        return $this->payerStreet;
    }

    public function getPayerPostalCode(): ?string
    {
        return $this->payerPostalCode;
    }

    public function getPayerCity(): ?string
    {
        return $this->payerCity;
    }

    public function getPayerCountryCode(): ?string
    {
        return $this->payerCountryCode;
    }

    /**
     * @param list<string>|null $street
     */
    public function setPayerFiscalIdentity(
        ?string $taxStatus,
        ?string $legalName,
        ?string $siren,
        ?string $vatNumber,
        ?string $electronicAddressScheme,
        ?string $electronicAddress,
        ?array $street,
        ?string $postalCode,
        ?string $city,
        ?string $countryCode,
    ): void {
        $this->payerTaxStatus               = $taxStatus;
        $this->payerLegalName               = $legalName;
        $this->payerSiren                   = $siren;
        $this->payerVatNumber               = $vatNumber;
        $this->payerElectronicAddressScheme = $electronicAddressScheme;
        $this->payerElectronicAddress       = $electronicAddress;
        $this->payerStreet                  = $street;
        $this->payerPostalCode              = $postalCode;
        $this->payerCity                    = $city;
        $this->payerCountryCode             = $countryCode;
    }
}
