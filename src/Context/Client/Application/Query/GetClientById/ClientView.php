<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientById;

final readonly class ClientView
{
    /**
     * @param list<ContactMethodDto> $contactMethods
     * @param list<ConsentDto>       $consents       active consents only
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
