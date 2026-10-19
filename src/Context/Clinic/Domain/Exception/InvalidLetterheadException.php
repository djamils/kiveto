<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Exception;

/**
 * A letterhead whose fiscal identity does not hold: a SIRET that is not one, or a
 * VAT number that does not belong to it. The field and the reason let the
 * caller say exactly what to fix.
 */
final class InvalidLetterheadException extends \DomainException
{
    public const string FIELD_SIRET      = 'siret';
    public const string FIELD_VAT_NUMBER = 'vatNumber';

    /** Not 14 digits, or not a VAT number shaped « FR » + key + SIREN. */
    public const string REASON_FORMAT = 'format';
    /** The SIREN or the SIRET fails its check digit. */
    public const string REASON_CHECKSUM = 'checksum';
    /** A VAT number was given without the SIRET it belongs to. */
    public const string REASON_MISSING = 'missing';
    /** The VAT number carries another company's SIREN. */
    public const string REASON_SIREN_MISMATCH = 'siren_mismatch';
    /** The VAT number's key does not match its SIREN. */
    public const string REASON_KEY = 'key';

    /**
     * @param self::FIELD_*  $field
     * @param self::REASON_* $reason
     */
    public function __construct(
        public readonly string $field,
        public readonly string $reason,
        string $input,
    ) {
        parent::__construct(\sprintf('Invalid letterhead %s "%s" (%s).', $field, $input, $reason));
    }
}
