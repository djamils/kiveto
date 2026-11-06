<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\CheckAccountToken;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Whether a link may still be used, before its page shows a form.
 * Answers an AccountTokenCheck.
 */
final readonly class CheckAccountToken implements QueryInterface
{
    public const string INVITATION     = 'INVITATION';
    public const string PASSWORD_RESET = 'PASSWORD_RESET';

    public const string CONSOLE_INVITATION = 'CONSOLE_INVITATION';

    /**
     * @param string $purpose self::INVITATION, self::PASSWORD_RESET or self::CONSOLE_INVITATION
     */
    public function __construct(
        public string $token,
        public string $purpose,
    ) {
    }
}
