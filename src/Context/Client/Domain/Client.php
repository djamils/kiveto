<?php

declare(strict_types=1);

namespace App\Context\Client\Domain;

use App\Context\Client\Domain\Event\ClientArchived;
use App\Context\Client\Domain\Event\ClientAuthorizedPersonChanged;
use App\Context\Client\Domain\Event\ClientCommunicationPreferencesUpdated;
use App\Context\Client\Domain\Event\ClientContactMethodsReplaced;
use App\Context\Client\Domain\Event\ClientCreated;
use App\Context\Client\Domain\Event\ClientIdentityUpdated;
use App\Context\Client\Domain\Event\ClientPostalAddressUpdated;
use App\Context\Client\Domain\Event\ClientTaxIdentifiersChanged;
use App\Context\Client\Domain\Event\ClientTaxProfileUpdated;
use App\Context\Client\Domain\Event\ClientTaxProfileVerified;
use App\Context\Client\Domain\Event\ClientUnarchived;
use App\Context\Client\Domain\Exception\ClientAlreadyArchivedException;
use App\Context\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Context\Client\Domain\Exception\ClientMustHaveAtLeastOneContactMethodException;
use App\Context\Client\Domain\Exception\DuplicateContactMethodException;
use App\Context\Client\Domain\Exception\PrimaryContactMethodConflictException;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\VerificationRequestOutcome;
use App\Context\Client\Domain\ValueObject\AuthorizedPerson;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationPreferences;
use App\Context\Client\Domain\ValueObject\Consent;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\Client\Domain\ValueObject\ContactMethodType;
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
    private ?PostalAddress $postalAddress       = null;
    private ?AuthorizedPerson $authorizedPerson = null;
    private CommunicationPreferences $communicationPreferences;
    private ClientTaxProfile $taxProfile;
    private TaxProfileVerification $taxProfileVerification;
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
        ?ClientTaxProfile $taxProfile = null,
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

        $client->communicationPreferences = CommunicationPreferences::none();
        $client->taxProfile               = $taxProfile ?? ClientTaxProfile::default();
        $client->taxProfileVerification   = TaxProfileVerification::unchecked();

        $client->recordDomainEvent(new ClientCreated(
            clientId: $id->toString(),
            clinicId: $clinicId->toString(),
            firstName: $identity->firstName,
            lastName: $identity->lastName,
            contactMethods: self::serializeContactMethods($contactMethods),
            legalName: $client->taxProfile->legalName(),
        ));

        if (null !== $client->taxProfile->siren()) {
            $client->taxIdentifiersChanged(ClientTaxProfile::default(), $createdAt);
        }

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
        ?AuthorizedPerson $authorizedPerson = null,
        ?CommunicationPreferences $communicationPreferences = null,
        ?ClientTaxProfile $taxProfile = null,
        ?TaxProfileVerification $taxProfileVerification = null,
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

        $client->authorizedPerson         = $authorizedPerson;
        $client->communicationPreferences = $communicationPreferences ?? CommunicationPreferences::none();
        $client->taxProfile               = $taxProfile ?? ClientTaxProfile::default();
        $client->taxProfileVerification   = $taxProfileVerification ?? TaxProfileVerification::unchecked();

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
            civility: $newIdentity->civility->value,
            legalName: $this->taxProfile->legalName(),
        ));
    }

    public function updateTaxProfile(ClientTaxProfile $taxProfile, \DateTimeImmutable $now): void
    {
        $this->ensureNotArchived();

        if ($this->taxProfile->equals($taxProfile)) {
            return;
        }

        $previous         = $this->taxProfile;
        $this->taxProfile = $taxProfile;
        $this->updatedAt  = $now;

        $this->recordDomainEvent(new ClientTaxProfileUpdated(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            status: $taxProfile->status()->value,
            legalName: $taxProfile->legalName(),
            siren: $taxProfile->siren()?->value(),
            vatNumber: $taxProfile->vatNumber()?->value(),
            firstName: $this->identity->firstName,
            lastName: $this->identity->lastName,
        ));

        // A legal-name-only change (e.g. « Utiliser le nom officiel ») keeps the verification.
        $sirenChanged = $previous->siren()?->value() !== $taxProfile->siren()?->value();
        $vatChanged   = $previous->vatNumber()?->value() !== $taxProfile->vatNumber()?->value();

        if ($sirenChanged || $vatChanged) {
            $this->taxIdentifiersChanged($previous, $now);
        }
    }

    /**
     * Stores the answer of the registry and VIES for the identifiers that were checked.
     *
     * An answer for identifiers that are no longer current changes nothing (the
     * newer request stays awaited); a client that can no longer be verified
     * (archived, not a French business) stores nothing and awaits nothing.
     *
     * @return bool whether the client changed (and must be saved)
     */
    public function recordTaxProfileVerification(
        string $checkedSiren,
        ?string $checkedVatNumber,
        TaxProfileVerification $result,
    ): bool {
        if (!$this->isTaxProfileVerifiable()) {
            if (null === $this->taxProfileVerification->requestedAt()) {
                return false;
            }

            $this->taxProfileVerification = $this->taxProfileVerification->withRequestedAt(null);

            return true;
        }

        $isCurrent = $checkedSiren === $this->taxProfile->siren()?->value()
            && $checkedVatNumber === $this->taxProfile->vatNumber()?->value();

        if (!$isCurrent) {
            return false;
        }

        // An outage never overwrites a definite answer: it is kept with its date, so a stale
        // profile stays stale (and is retried) and its warnings stay shown.
        $previous = $this->taxProfileVerification;
        $company  = $this->keepsDefinite($previous->company()->outcome()->isDefinite(), $result->company()->outcome()->isDefinite())
            ? $previous->company() : $result->company();
        $vat = $this->keepsDefinite($previous->vat()->outcome()->isDefinite(), $result->vat()->outcome()->isDefinite())
            ? $previous->vat() : $result->vat();

        $this->taxProfileVerification = TaxProfileVerification::of($company, $vat);

        $this->recordDomainEvent(new ClientTaxProfileVerified(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            companyOutcome: $company->outcome()->value,
            registryName: $company->registryName(),
            companyCheckedAt: $company->checkedAt()?->format(\DATE_ATOM),
            vatOutcome: $vat->outcome()->value,
            vatCheckedAt: $vat->checkedAt()?->format(\DATE_ATOM),
        ));

        return true;
    }

    /**
     * « Revérifier »: marks a new verification as requested unless one is still awaited.
     */
    public function requestTaxProfileVerification(\DateTimeImmutable $now): VerificationRequestOutcome
    {
        if ($this->isArchived()) {
            return VerificationRequestOutcome::ARCHIVED;
        }

        if (!$this->isTaxProfileVerifiable()) {
            return VerificationRequestOutcome::NOT_APPLICABLE;
        }

        if ($this->taxProfileVerification->isPending($now)) {
            return VerificationRequestOutcome::ALREADY_PENDING;
        }

        $this->taxProfileVerification = $this->taxProfileVerification->withRequestedAt($now);

        return VerificationRequestOutcome::QUEUED;
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

    /**
     * Designating the same person again keeps the date of the original
     * authorization; null withdraws the authorization.
     */
    public function changeAuthorizedPerson(?AuthorizedPerson $person, \DateTimeImmutable $now): void
    {
        $this->ensureNotArchived();

        if (null !== $person && null !== $this->authorizedPerson && $person->isSamePersonAs($this->authorizedPerson)) {
            $person = new AuthorizedPerson(
                $person->civility,
                $person->firstName,
                $person->lastName,
                $person->relationship,
                $person->phoneNumber,
                $this->authorizedPerson->authorizedAt,
            );
        }

        $this->authorizedPerson = $person;
        $this->updatedAt        = $now;

        $this->recordDomainEvent(new ClientAuthorizedPersonChanged(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            authorizedPerson: null === $person ? null : [
                'civility'     => $person->civility->value,
                'firstName'    => $person->firstName,
                'lastName'     => $person->lastName,
                'relationship' => $person->relationship->value,
                'phoneNumber'  => $person->phoneNumber?->toString(),
                'authorizedAt' => $person->authorizedAt->format(\DATE_ATOM),
            ],
        ));
    }

    /**
     * @param array<string, list<ContactChannel>> $channelsByConsentKind keyed by ConsentKind value
     */
    public function updateCommunicationPreferences(
        ?ContactChannel $preferredChannel,
        array $channelsByConsentKind,
        \DateTimeImmutable $now,
    ): void {
        $this->ensureNotArchived();

        $this->communicationPreferences = $this->communicationPreferences->revise(
            $preferredChannel,
            $channelsByConsentKind,
            $now,
        );
        $this->updatedAt = $now;

        $this->recordDomainEvent(new ClientCommunicationPreferencesUpdated(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            preferredChannel: $preferredChannel?->value,
            consents: array_map(
                static fn (Consent $consent): array => [
                    'kind'      => $consent->kind->value,
                    'channels'  => array_map(static fn (ContactChannel $c): string => $c->value, $consent->channels),
                    'grantedAt' => $consent->grantedAt->format(\DATE_ATOM),
                ],
                $this->communicationPreferences->consents,
            ),
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

    public function authorizedPerson(): ?AuthorizedPerson
    {
        return $this->authorizedPerson;
    }

    public function communicationPreferences(): CommunicationPreferences
    {
        return $this->communicationPreferences;
    }

    public function taxProfile(): ClientTaxProfile
    {
        return $this->taxProfile;
    }

    public function taxProfileVerification(): TaxProfileVerification
    {
        return $this->taxProfileVerification;
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

    private function keepsDefinite(bool $previousIsDefinite, bool $newIsDefinite): bool
    {
        return $previousIsDefinite && !$newIsDefinite;
    }

    private function isTaxProfileVerifiable(): bool
    {
        return !$this->isArchived()
            && ClientTaxStatus::FRENCH_BUSINESS === $this->taxProfile->status()
            && null !== $this->taxProfile->siren();
    }

    /**
     * Resets the verification (requested again when the new identifiers can be checked).
     */
    private function taxIdentifiersChanged(ClientTaxProfile $previous, \DateTimeImmutable $now): void
    {
        $this->taxProfileVerification = TaxProfileVerification::unchecked($this->isTaxProfileVerifiable() ? $now : null);

        $this->recordDomainEvent(new ClientTaxIdentifiersChanged(
            clientId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            taxStatus: $this->taxProfile->status()->value,
            previousSiren: $previous->siren()?->value(),
            siren: $this->taxProfile->siren()?->value(),
            previousVatNumber: $previous->vatNumber()?->value(),
            vatNumber: $this->taxProfile->vatNumber()?->value(),
        ));
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
