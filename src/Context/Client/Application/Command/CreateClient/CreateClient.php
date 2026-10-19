<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\CreateClient;

use App\Context\Client\Application\Command\UpdateClientPostalAddress\PostalAddressDto;

/**
 * Opens a client record. Beyond the identity and the contact methods, the
 * directory's « Nouveau client » window gives at once the civility, the
 * postal address, how the client wants to be reminded (and what they agree
 * to receive), the animals they come with and, for a structure, its tax
 * profile (legal name, SIREN, VAT number): all of it lands in one go.
 */
final readonly class CreateClient
{
    /**
     * @param list<ContactMethodDto>      $contactMethods
     * @param array<string, list<string>> $channelsByConsentKind consent kind => channels the client agreed to
     * @param list<NewAnimalDto>          $animals               registered in the Animal context, owned by the new client
     */
    public function __construct(
        public string $clinicId,
        public string $firstName,
        public string $lastName,
        public array $contactMethods,
        public string $civility = 'unspecified',
        public ?PostalAddressDto $postalAddress = null,
        public ?string $preferredChannel = null,
        public array $channelsByConsentKind = [],
        public array $animals = [],
        // Null opens a plain individual record.
        public ?ClientTaxProfileDto $taxProfile = null,
    ) {
    }
}
