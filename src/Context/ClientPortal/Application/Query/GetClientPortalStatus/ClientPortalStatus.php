<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetClientPortalStatus;

/**
 * - `not_invited`: never invited (or only invitations revoked before any link);
 * - `invited`: an invitation can be accepted (`sentAt`, `expiresAt`);
 * - `expired`: the pending invitation is past its expiry (`sentAt`, `expiresAt`);
 * - `active`: an owner account is linked (`linkedAt`);
 * - `revoked`: the access was withdrawn (`revokedAt`) and no invitation was
 *   sent since.
 */
final readonly class ClientPortalStatus
{
    public const string NOT_INVITED = 'not_invited';
    public const string INVITED     = 'invited';
    public const string EXPIRED     = 'expired';
    public const string ACTIVE      = 'active';
    public const string REVOKED     = 'revoked';

    public function __construct(
        public string $state,
        public ?\DateTimeImmutable $sentAt = null,
        public ?\DateTimeImmutable $expiresAt = null,
        public ?\DateTimeImmutable $linkedAt = null,
        public ?\DateTimeImmutable $revokedAt = null,
    ) {
    }
}
