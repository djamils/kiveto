<?php

declare(strict_types=1);

namespace App\Client\Domain;

use App\Client\Domain\Event\ClientArchived;
use App\Client\Domain\Event\ClientContactMethodsReplaced;
use App\Client\Domain\Event\ClientCreated;
use App\Client\Domain\Event\ClientIdentityUpdated;
use App\Client\Domain\Event\ClientPostalAddressUpdated;
use App\Client\Domain\Event\ClientUnarchived;
use App\Client\Domain\Exception\ClientAlreadyArchivedException;
use App\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Client\Domain\Exception\ClientMustHaveAtLeastOneContactMethodException;
use App\Client\Domain\Exception\DuplicateContactMethodException;
use App\Client\Domain\Exception\PrimaryContactMethodConflictException;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\PostalAddress;

final class Client extends AggregateRoot
{
    private ClientId $id;
    private ClinicId $clinicId;
    private ClientIdentity $identity;
    private ClientStatus $status;
    /** @var list<ContactMethod> */
    private array $contactMethods;
    private ?PostalAddress $postalAddress = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    /**
     * @param list<ContactMethod> $contactMethods
     */
    public static function create(
        ClientId $id,
        ClinicId $clinicId,
        ClientIdentity $identity,
        array $contactMethods,
        \DateTimeImmutable $createdAt,
    ): self {
        self::validateContactMethods($contactMethods);

        $client                 = new self();
        $client->id             = $id;
        $client->clinicId       = $clinicId;
        $client->identity       = $identity;
        $client->status         = ClientStatus::ACTIVE;
        $client->contactMethods = $contactMethods;
        $client->createdAt      = $createdAt;
        $client->updatedAt      = $createdAt;

        $client->recordDomainEvent(new ClientCreated(
            clientId: $id->toString(),
            clinicId: $clinicId->toString(),
            firstName: $identity->firstName,
            lastName: $identity->lastName,
            contactMethods: self::serializeContactMethods($contactMethods),
        ));

        return $client;
    }

    /**
     * @param list<ContactMethod> $contactMethods
     */
    public static function reconstitute(
        ClientId $id,
        ClinicId $clinicId,
        ClientIdentity $identity,
        ClientStatus $status,
        array $contactMethods,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?PostalAddress $postalAddress = null,
    ): self {
        $client                 = new self();
        $client->id             = $id;
        $client->clinicId       = $clinicId;
        $client->identity       = $identity;
        $client->status         = $status;
        $client->contactMethods = $contactMethods;
        $client->postalAddress  = $postalAddress;
        $client->createdAt      = $createdAt;
        $client->updatedAt      = $updatedAt;

        return $client;
    }

    public function updateIdentity(ClientIdentity $newIdentity, \DateTimeImmutable $now): void
    {
        $this->ensureNotArchived();

        $this->identity  = $newIdentity;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new ClientIdentityUpdated(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            firstName: $newIdentity->firstName,
            lastName: $newIdentity->lastName,
        ));
    }

    /**
     * @param list<ContactMethod> $newContactMethods
     */
    public function replaceContactMethods(array $newContactMethods, \DateTimeImmutable $now): void
    {
        $this->ensureNotArchived();
        self::validateContactMethods($newContactMethods);

        $this->contactMethods = $newContactMethods;
        $this->updatedAt      = $now;

        $this->recordDomainEvent(new ClientContactMethodsReplaced(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            contactMethods: self::serializeContactMethods($newContactMethods),
        ));
    }

    public function archive(\DateTimeImmutable $now): void
    {
        if (ClientStatus::ARCHIVED === $this->status) {
            throw new ClientAlreadyArchivedException($this->id->toString());
        }

        $this->status    = ClientStatus::ARCHIVED;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new ClientArchived(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function unarchive(\DateTimeImmutable $now): void
    {
        if (ClientStatus::ACTIVE === $this->status) {
            throw new \DomainException(\sprintf('Client "%s" is already active.', $this->id->toString()));
        }

        $this->status    = ClientStatus::ACTIVE;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new ClientUnarchived(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function updatePostalAddress(?PostalAddress $newAddress, \DateTimeImmutable $now): void
    {
        $this->ensureNotArchived();

        $this->postalAddress = $newAddress;
        $this->updatedAt     = $now;

        $this->recordDomainEvent(new ClientPostalAddressUpdated(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            postalAddress: null === $newAddress ? null : self::serializePostalAddress($newAddress),
        ));
    }

    public function id(): ClientId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function identity(): ClientIdentity
    {
        return $this->identity;
    }

    public function status(): ClientStatus
    {
        return $this->status;
    }

    /**
     * @return list<ContactMethod>
     */
    public function contactMethods(): array
    {
        return $this->contactMethods;
    }

    public function postalAddress(): ?PostalAddress
    {
        return $this->postalAddress;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isArchived(): bool
    {
        return ClientStatus::ARCHIVED === $this->status;
    }

    private function ensureNotArchived(): void
    {
        if ($this->isArchived()) {
            throw new ClientArchivedCannotBeModifiedException($this->id->toString());
        }
    }

    /**
     * @param list<ContactMethod> $contactMethods
     */
    private static function validateContactMethods(array $contactMethods): void
    {
        if ([] === $contactMethods) {
            throw new ClientMustHaveAtLeastOneContactMethodException();
        }

        $primaryPhones = 0;
        $primaryEmails = 0;
        $seen          = [];

        foreach ($contactMethods as $method) {
            $key = \sprintf('%s:%s', $method->type->value, $method->value);

            if (isset($seen[$key])) {
                throw new DuplicateContactMethodException(
                    $method->type->value,
                    $method->value,
                );
            }

            $seen[$key] = true;

            if ($method->isPrimary) {
                if (ContactMethodType::PHONE === $method->type) {
                    ++$primaryPhones;
                } elseif (ContactMethodType::EMAIL === $method->type) {
                    ++$primaryEmails;
                }
            }
        }

        if ($primaryPhones > 1) {
            throw PrimaryContactMethodConflictException::forPhones();
        }

        if ($primaryEmails > 1) {
            throw PrimaryContactMethodConflictException::forEmails();
        }
    }

    /**
     * @param list<ContactMethod> $contactMethods
     *
     * @return list<array{type: string, label: string, value: string, isPrimary: bool}>
     */
    private static function serializeContactMethods(array $contactMethods): array
    {
        return array_map(
            static fn (ContactMethod $method): array => [
                'type'      => $method->type->value,
                'label'     => $method->label->value,
                'value'     => $method->value,
                'isPrimary' => $method->isPrimary,
            ],
            $contactMethods,
        );
    }

    /**
     * @return array{
     *     streetLine1: string,
     *     streetLine2: string|null,
     *     postalCode: string|null,
     *     city: string,
     *     region: string|null,
     *     countryCode: string
     * }
     */
    private static function serializePostalAddress(PostalAddress $address): array
    {
        return [
            'streetLine1' => $address->streetLine1,
            'streetLine2' => $address->streetLine2,
            'postalCode'  => $address->postalCode,
            'city'        => $address->city,
            'region'      => $address->region,
            'countryCode' => $address->countryCode,
        ];
    }
}
