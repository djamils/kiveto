<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final class InvalidSirenException extends \InvalidArgumentException
{
    public const string FORMAT   = 'format';
    public const string CHECKSUM = 'checksum';

    /**
     * @param self::FORMAT|self::CHECKSUM $reason
     */
    public function __construct(public readonly string $reason, string $input)
    {
        parent::__construct(\sprintf('Invalid SIREN "%s" (%s).', $input, $reason));
    }
}
