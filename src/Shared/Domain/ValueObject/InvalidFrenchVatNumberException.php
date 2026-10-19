<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final class InvalidFrenchVatNumberException extends \InvalidArgumentException
{
    public const string FORMAT         = 'format';
    public const string SIREN_MISMATCH = 'siren_mismatch';
    public const string KEY            = 'key';

    /**
     * @param self::FORMAT|self::SIREN_MISMATCH|self::KEY $reason
     */
    public function __construct(public readonly string $reason, string $input)
    {
        parent::__construct(\sprintf('Invalid French VAT number "%s" (%s).', $input, $reason));
    }
}
