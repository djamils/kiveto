<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNotificationContact;

use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethodType;
use App\Context\Client\Domain\ValueObject\MobileNumberPolicy;

/**
 * How a client can be reached by an automated message: the addresses on
 * file, the preferred channel and the consents. It crosses contexts through
 * the query bus, so it carries scalars and arrays only; the consent rules
 * (« Consent rules » of the notifications spec) are its two helpers.
 *
 * Channels and consent kinds are the Client values: `email`, `sms`;
 * `appointment_reminders`, `billing`, `clinical_documents`…
 */
final readonly class ClientNotificationContact
{
    public const string CHANNEL_EMAIL = 'email';
    public const string CHANNEL_SMS   = 'sms';

    public const string APPOINTMENT_REMINDERS = 'appointment_reminders';
    public const string BILLING               = 'billing';
    public const string CLINICAL_DOCUMENTS    = 'clinical_documents';

    /**
     * @param string                      $civility « Mme », « M. » or '' when not given
     * @param string|null                 $mobile   E.164
     * @param array<string, list<string>> $consents channels by consent kind, active consents only
     */
    public function __construct(
        public string $clientId,
        public string $displayName,
        public string $civility,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $mobile,
        public ?string $preferredChannel,
        public array $consents,
        public bool $archived,
    ) {
    }

    /**
     * Picks the addresses out of the client's contact methods: the primary
     * e-mail, else the first one; the primary phone when it can receive an
     * SMS, else the first phone that can.
     *
     * @param list<array{type: string, label: string, value: string, isPrimary: bool}> $contactMethods in record order
     * @param array<string, list<string>>                                              $consents
     */
    public static function compose(
        string $clientId,
        string $firstName,
        string $lastName,
        ?string $legalName,
        string $civility,
        bool $archived,
        array $contactMethods,
        ?string $preferredChannel,
        array $consents,
    ): self {
        // Primary methods first, the record order kept otherwise (stable sort).
        usort($contactMethods, static fn (array $a, array $b): int => $b['isPrimary'] <=> $a['isPrimary']);

        $email  = null;
        $mobile = null;

        foreach ($contactMethods as $method) {
            if (ContactMethodType::EMAIL->value === $method['type']) {
                $email ??= $method['value'];
            } elseif (MobileNumberPolicy::canReceiveSms($method['value'], ContactLabel::tryFrom($method['label']) ?? ContactLabel::OTHER)) {
                $mobile ??= $method['value'];
            }
        }

        $fullName = trim($firstName . ' ' . $lastName);

        return new self(
            clientId: $clientId,
            displayName: null !== $legalName && '' !== trim($legalName) ? trim($legalName) : $fullName,
            civility: (Civility::tryFrom($civility) ?? Civility::UNSPECIFIED)->honorific(),
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            mobile: $mobile,
            preferredChannel: $preferredChannel,
            consents: $consents,
            archived: $archived,
        );
    }

    /** « Mme Lambert », or « Hélène Lambert » when the civility is not given. */
    public function formalName(): string
    {
        return '' === $this->civility
            ? trim($this->firstName . ' ' . $this->lastName)
            : $this->civility . ' ' . $this->lastName;
    }

    /**
     * The channels the client agreed to for this kind of message and has an
     * address for (`email` and / or `sms`), in the consent's order.
     *
     * @return list<string>
     */
    public function channelsFor(string $consentKind): array
    {
        $kind = ConsentKind::from($consentKind);

        return array_values(array_filter(
            $this->consents[$kind->value] ?? [],
            $this->reachableOn(...),
        ));
    }

    /**
     * The single channel of a transactional message (appointment
     * confirmation, change, cancellation): the preferred channel when its
     * address exists, else e-mail, else SMS, else none.
     */
    public function transactionalChannel(): ?string
    {
        if (null !== $this->preferredChannel && $this->reachableOn($this->preferredChannel)) {
            return $this->preferredChannel;
        }

        if (null !== $this->email) {
            return self::CHANNEL_EMAIL;
        }

        return null !== $this->mobile ? self::CHANNEL_SMS : null;
    }

    private function reachableOn(string $channel): bool
    {
        return match ($channel) {
            self::CHANNEL_EMAIL => null !== $this->email,
            self::CHANNEL_SMS   => null !== $this->mobile,
            default             => false,
        };
    }
}
