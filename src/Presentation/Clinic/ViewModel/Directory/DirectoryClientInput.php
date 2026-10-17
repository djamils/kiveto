<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Directory;

use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\UpdateClientPostalAddress\PostalAddressDto;
use App\Presentation\Clinic\ViewModel\Client\TaxProfileInput;
use App\Presentation\Clinic\ViewModel\Flux\DeskRegistration;

/**
 * The client of the directory's « Nouveau client » window, as its page
 * module normalised it: civility, NOM typed in capitals, Prénom, a mobile
 * and/or a landline (one valid phone at least), an e-mail, an optional
 * postal address, the channel of the vaccine and appointment reminders, the
 * clinic's news, and the tax profile of a structure (shared block, see
 * TaxProfileInput). The checks the window runs are run again here, in its
 * words.
 */
final readonly class DirectoryClientInput
{
    public const string REMINDERS_SMS   = 'sms';
    public const string REMINDERS_EMAIL = 'mail';
    public const string REMINDERS_NONE  = 'aucun';

    private function __construct(
        public string $civility,
        public string $lastName,
        public string $firstName,
        public string $mobile,
        public string $landline,
        public string $email,
        public string $street,
        public string $complement,
        public string $postalCode,
        public string $city,
        public string $reminders,
        public bool $news,
        public bool $household,
        public ?TaxProfileInput $taxProfile = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    public static function parse(array $raw): self|string
    {
        $text   = static fn (string $key, int $max): string => mb_substr(mb_trim((string) preg_replace('/\s+/u', ' ', \is_string($raw[$key] ?? null) ? $raw[$key] : '')), 0, $max);
        $digits = static fn (string $key, int $max): string => substr((string) preg_replace('/\D+/', '', \is_string($raw[$key] ?? null) ? $raw[$key] : ''), 0, $max);

        $civility = ['M.' => 'mister', 'Mme' => 'madam'][$text('civ', 3)] ?? null;
        $lastName = self::lastName($text('nom', 40));
        $first    = self::firstName($text('pre', 40));
        $mobile   = $digits('mob', 10);
        $landline = $digits('fixe', 10);
        $email    = mb_strtolower((string) preg_replace('/\s+/u', '', $text('mail', 80)));
        $street   = $text('rue', 80);
        $more     = $text('cpl', 60);
        $postal   = $digits('cp', 5);
        $city     = $text('ville', 80);

        $mobileOk   = self::mobileOk($mobile);
        $landlineOk = self::landlineOk($landline);
        $tax        = TaxProfileInput::fromArray($raw);

        $error = $tax?->firstError() ?? match (true) {
            null === $civility       => 'Civilité requise',
            mb_strlen($lastName) < 2 => 'Nom requis',
            // The client record needs a first name.
            mb_strlen($first) < 2                                                => 'Prénom requis',
            !$mobileOk && !$landlineOk                                           => '' !== $mobile || '' !== $landline ? 'Téléphone invalide' : 'Un téléphone au moins',
            '' !== $mobile && !$mobileOk                                         => 'Mobile invalide',
            '' !== $landline && !$landlineOk                                     => 'Fixe invalide',
            '' !== $email && !self::emailOk($email)                              => 'E-mail invalide',
            ('' !== $street || '' !== $postal || '' !== $more) && '' === $street => 'Adresse : n° et voie manquants',
            '' !== $street && 5 !== \strlen($postal)                             => 'Adresse : code postal invalide',
            '' !== $street && '' === $city                                       => 'Adresse : choisissez la commune',
            default                                                              => null,
        };

        if (null !== $error) {
            return $error;
        }

        \assert(null !== $civility);

        $reminders = $text('rap', 5);
        $available = [
            self::REMINDERS_SMS   => $mobileOk,
            self::REMINDERS_EMAIL => '' !== $email,
            self::REMINDERS_NONE  => true,
        ];

        return new self(
            civility: $civility,
            lastName: $lastName,
            firstName: $first,
            mobile: $mobile,
            landline: $landline,
            email: $email,
            street: $street,
            complement: $more,
            postalCode: $postal,
            city: $city,
            // A channel the client cannot be reached on is no reminder at all.
            reminders: true === ($available[$reminders] ?? false) ? $reminders : self::REMINDERS_NONE,
            news: true === ($raw['news'] ?? false),
            household: true === ($raw['autre'] ?? false),
            taxProfile: $tax,
        );
    }

    public static function mobileOk(string $digits): bool
    {
        return 1 === preg_match('/^0[67]\d{8}$/', $digits);
    }

    public static function landlineOk(string $digits): bool
    {
        return 1 === preg_match('/^0[1-59]\d{8}$/', $digits);
    }

    public static function emailOk(string $email): bool
    {
        return 1 === preg_match('/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i', $email);
    }

    /** « M. Jean Dupont », or the legal name of a structure. */
    public function title(): string
    {
        return $this->taxProfile->legalName
            ?? ('mister' === $this->civility ? 'M.' : 'Mme') . ' ' . $this->firstName . ' ' . $this->lastName;
    }

    /**
     * The numbers as the record stores them (« +33612345678 »).
     *
     * @return list<string>
     */
    public function phones(): array
    {
        return array_values(array_map(
            DeskRegistration::e164(...),
            array_filter([$this->mobile, $this->landline], static fn (string $phone): bool => '' !== $phone),
        ));
    }

    /**
     * The mobile first (the main number), then the landline, then the e-mail.
     *
     * @return list<ContactMethodDto>
     */
    public function contactMethods(): array
    {
        $methods = [];

        if ('' !== $this->mobile) {
            $methods[] = new ContactMethodDto('phone', 'mobile', DeskRegistration::e164($this->mobile), true);
        }
        if ('' !== $this->landline) {
            $methods[] = new ContactMethodDto('phone', 'home', DeskRegistration::e164($this->landline), '' === $this->mobile);
        }
        if ('' !== $this->email) {
            $methods[] = new ContactMethodDto('email', 'home', $this->email, true);
        }

        return $methods;
    }

    public function postalAddress(): ?PostalAddressDto
    {
        if ('' === $this->street) {
            return null;
        }

        return new PostalAddressDto(
            streetLine1: $this->street,
            city: $this->city,
            countryCode: 'FR',
            streetLine2: '' === $this->complement ? null : $this->complement,
            postalCode: $this->postalCode,
        );
    }

    /** The reminders' channel, the client's preferred one. */
    public function preferredChannel(): ?string
    {
        return match ($this->reminders) {
            self::REMINDERS_SMS   => 'sms',
            self::REMINDERS_EMAIL => 'email',
            default               => null,
        };
    }

    /**
     * Reminders of vaccines and appointments on the chosen channel; the
     * clinic's news on every channel the client can receive them on.
     *
     * @return array<string, list<string>>
     */
    public function consents(): array
    {
        $consents = [];
        $channel  = $this->preferredChannel();

        if (null !== $channel) {
            $consents['appointment_reminders'] = [$channel];
            $consents['vaccination_reminders'] = [$channel];
        }

        $news = array_keys(array_filter(['sms' => self::mobileOk($this->mobile), 'email' => '' !== $this->email]));

        if ($this->news && [] !== $news) {
            $consents['marketing'] = $news;
        }

        return $consents;
    }

    /** « DUPONT » or « LE GALL » typed in capitals is kept as « Dupont », « Le Gall ». */
    private static function lastName(string $typed): string
    {
        return mb_convert_case(mb_strtolower((string) preg_replace("/[^\\p{L}' -]/u", '', $typed)), \MB_CASE_TITLE);
    }

    /** « jean-pierre » is kept as « Jean-Pierre ». */
    private static function firstName(string $typed): string
    {
        return (string) preg_replace_callback(
            "/(^|[\\s'-])(\\p{Ll})/u",
            static fn (array $m): string => $m[1] . mb_strtoupper($m[2]),
            (string) preg_replace("/[^\\p{L}' -]/u", '', $typed),
        );
    }
}
