<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Exception;

final class InvalidClientTaxProfileException extends \InvalidArgumentException
{
    public const string LEGAL_NAME_REQUIRED = 'legal_name_required';
    public const string LEGAL_NAME_TOO_LONG = 'legal_name_too_long';
    public const string SIREN_REQUIRED      = 'siren_required';
    public const string STATUS_UNKNOWN      = 'status_unknown';

    /**
     * @param self::LEGAL_NAME_REQUIRED|self::LEGAL_NAME_TOO_LONG|self::SIREN_REQUIRED|self::STATUS_UNKNOWN $reason
     */
    public function __construct(public readonly string $reason)
    {
        parent::__construct(\sprintf('Invalid client tax profile (%s).', $reason));
    }
}
