<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Client;

use App\Context\Billing\Application\Command\RecordCounterSale\ClientTaxProfileInput;
use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\InvalidFrenchVatNumberException;
use App\Shared\Domain\ValueObject\InvalidSirenException;
use App\Shared\Domain\ValueObject\Siren;

/**
 * The shared tax-profile block (« Particulier ou structure non assujettie » /
 * « Professionnel assujetti à la TVA »), as every window that mounts it sends
 * it: `fisc` (`ind`|`pro`), `rs` (legal name), `siren`, `tva`. The single
 * place its rules are checked and turned into the Client or Billing command
 * input; SIREN and VAT number are only read for a professional.
 */
final readonly class TaxProfileInput
{
    public const string INDIVIDUAL   = 'ind';
    public const string PROFESSIONAL = 'pro';

    // As the Client context's ClientTaxProfile and the `legal_name` columns.
    private const int LEGAL_NAME_MAX = 255;

    private function __construct(
        public string $fisc,
        public ?string $legalName,
        public string $siren,
        public string $vatNumber,
    ) {
    }

    /**
     * Null when the form carries no tax-profile block at all.
     *
     * @param array<array-key, mixed> $raw
     */
    public static function fromArray(array $raw): ?self
    {
        $fisc = $raw['fisc'] ?? null;

        if (!\in_array($fisc, [self::INDIVIDUAL, self::PROFESSIONAL], true)) {
            return null;
        }

        $text      = static fn (string $key): string => mb_trim((string) preg_replace('/\s+/u', ' ', \is_string($raw[$key] ?? null) ? $raw[$key] : ''));
        $legalName = $text('rs');
        $pro       = self::PROFESSIONAL === $fisc;

        return new self(
            fisc: $fisc,
            legalName: '' === $legalName ? null : $legalName,
            siren: $pro ? $text('siren') : '',
            vatNumber: $pro ? $text('tva') : '',
        );
    }

    public function isProfessional(): bool
    {
        return self::PROFESSIONAL === $this->fisc;
    }

    /**
     * What to correct, field by field, in the block's words. A window that
     * only creates structures (the counter « Professionnel » tab) asks for
     * the legal name whatever the status.
     *
     * @return array<string, string> `rs`, `siren` or `tva` => message
     */
    public function errors(bool $structureOnly = false): array
    {
        $errors = [];

        if (null === $this->legalName && ($structureOnly || $this->isProfessional())) {
            $errors['rs'] = 'La raison sociale est obligatoire.';
        }

        if (null !== $this->legalName && mb_strlen($this->legalName) > self::LEGAL_NAME_MAX) {
            $errors['rs'] = \sprintf('La raison sociale ne doit pas dépasser %d caractères.', self::LEGAL_NAME_MAX);
        }

        if (!$this->isProfessional()) {
            return $errors;
        }

        try {
            $siren = Siren::fromInput($this->siren);
        } catch (InvalidSirenException $e) {
            $errors['siren'] = InvalidSirenException::CHECKSUM === $e->reason
                ? 'SIREN invalide : clé de contrôle incorrecte.'
                : 'SIREN invalide : 9 chiffres attendus.';

            return $errors;
        }

        if ('' !== $this->vatNumber) {
            try {
                FrenchVatNumber::fromInput($this->vatNumber, $siren);
            } catch (InvalidFrenchVatNumberException $e) {
                $errors['tva'] = match ($e->reason) {
                    InvalidFrenchVatNumberException::SIREN_MISMATCH => 'N° de TVA : il ne correspond pas au SIREN.',
                    InvalidFrenchVatNumberException::KEY            => 'N° de TVA invalide : clé incorrecte.',
                    default                                         => 'N° de TVA invalide : format FR + 2 chiffres + SIREN.',
                };
            }
        }

        return $errors;
    }

    /** The first thing to correct, or null. */
    public function firstError(bool $structureOnly = false): ?string
    {
        $errors = $this->errors($structureOnly);

        return [] === $errors ? null : reset($errors);
    }

    /**
     * The profile for the Client context, SIREN and VAT number normalised
     * (« 523456788 », « FR31523456788 ») once valid.
     */
    public function toClientDto(): ClientTaxProfileDto
    {
        if (!$this->isProfessional()) {
            return new ClientTaxProfileDto('individual', $this->legalName);
        }

        $siren     = $this->siren;
        $vatNumber = '' === $this->vatNumber ? null : $this->vatNumber;

        if ([] === $this->errors()) {
            $valid     = Siren::fromInput($siren);
            $siren     = $valid->value();
            $vatNumber = null === $vatNumber ? null : FrenchVatNumber::fromInput($vatNumber, $valid)->value();
        }

        return new ClientTaxProfileDto('french_business', $this->legalName, $siren, $vatNumber);
    }

    public function toBillingInput(): ClientTaxProfileInput
    {
        $dto = $this->toClientDto();

        return new ClientTaxProfileInput($dto->status, $dto->legalName, $dto->siren, $dto->vatNumber);
    }
}
