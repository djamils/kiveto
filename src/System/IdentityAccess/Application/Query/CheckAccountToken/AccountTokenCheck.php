<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\CheckAccountToken;

final readonly class AccountTokenCheck
{
    public const string VALID          = 'VALID';
    public const string INVALID        = 'INVALID';
    public const string ALREADY_ACTIVE = 'ALREADY_ACTIVE';

    /**
     * @param self::VALID|self::INVALID|self::ALREADY_ACTIVE $state
     * @param string|null                                    $email the account's address, for a usable link
     */
    public function __construct(
        public string $state,
        public ?string $email = null,
    ) {
    }

    public function isValid(): bool
    {
        return self::VALID === $this->state;
    }
}
