<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientById;

final readonly class ClientView
{
    /**
     * @param list<ContactMethodDto> $contactMethods
     * @param list<ConsentDto>       $consents       active consents only
     * @param list<string>           $taxWarnings    TaxProfileWarning values, in display order
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $firstName,
        public string $lastName,
        public string $status,
        public array $contactMethods,
        public ?PostalAddressDto $postalAddress,
        public string $createdAt,
        public string $updatedAt,
        public ?AuthorizedPersonDto $authorizedPerson = null,
        public ?string $preferredChannel = null,
        public array $consents = [],
        public string $civility = 'unspecified',
        public string $taxStatus = 'individual',
        public ?string $legalName = null,
        public ?string $siren = null,
        public ?string $vatNumber = null,
        public string $taxCompanyCheck = 'unchecked',
        public ?string $taxRegistryName = null,
        public ?string $taxCompanyCheckedAt = null,
        public string $taxVatCheck = 'unchecked',
        public ?string $taxVatCheckedAt = null,
        public bool $taxVerificationPending = false,
        public array $taxWarnings = [],
    ) {
    }

    public function consentFor(string $kind): ?ConsentDto
    {
        foreach ($this->consents as $consent) {
            if ($consent->kind === $kind) {
                return $consent;
            }
        }

        return null;
    }

    public function fullName(): string
    {
        return trim(\sprintf('%s %s', $this->firstName, $this->lastName));
    }
}
