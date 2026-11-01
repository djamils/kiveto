<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use App\System\IdentityAccess\Application\Port\IssuedAccessToken;
use App\System\IdentityAccess\Application\Port\MobilePhonePolicyInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeGeneratorInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitation;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitationsInterface;
use App\System\IdentityAccess\Application\Port\UnknownNumberSmsBudgetInterface;

/**
 * Hand-written doubles of the Portal ports: predictable codes and a readable
 * code digest, plain access tokens, a switchable mobile policy and a
 * countable unknown-number budget (network, device and global layers).
 */
final class PortalFakes implements OtpCodeGeneratorInterface, OtpCodeHasherInterface, AccessTokenIssuerInterface, MobilePhonePolicyInterface, UnknownNumberSmsBudgetInterface, PendingPortalInvitationsInterface
{
    /** @var array<string, list<PendingPortalInvitation>> pending invitations by phone */
    public array $pending = [];

    /** @var list<string> codes handed out next; '123456' once exhausted */
    public array $nextCodes = [];

    public bool $mobile = true;

    /** @var list<string> phones checked against the policy */
    public array $policyChecks = [];

    /** SMS left in the global unknown-number budget. */
    public int $budget = 200;

    /** Global budget consumptions. */
    public int $budgetConsumptions = 0;

    /** @var array<string, int> SMS left per network key or device id; 20 / 5 when absent */
    public array $layerBudgets = [];

    /** @var list<string> layers consumed, in order: `network:<key>`, `device:<id>`, `global` */
    public array $budgetCalls = [];

    /** @var list<string> users an access token was issued for */
    public array $accessTokensFor = [];

    public function __construct(private readonly \DateTimeImmutable $accessTokenExpiresAt = new \DateTimeImmutable('2026-10-26 07:29:00'))
    {
    }

    public function generate(): string
    {
        return array_shift($this->nextCodes) ?? '123456';
    }

    /** A 64-character digest, the width of the stored column. */
    public function hash(string $code): string
    {
        return hash('sha256', 'otp:' . $code);
    }

    public function issue(string $userId): IssuedAccessToken
    {
        $this->accessTokensFor[] = $userId;

        return new IssuedAccessToken('jwt.' . $userId . '.' . \count($this->accessTokensFor), $this->accessTokenExpiresAt);
    }

    public function isPortalMobile(string $phone): bool
    {
        $this->policyChecks[] = $phone;

        return $this->mobile;
    }

    public function consumeNetwork(string $networkKey): bool
    {
        return $this->consumeLayer('network:' . $networkKey, 20);
    }

    public function consumeDevice(string $deviceId): bool
    {
        return $this->consumeLayer('device:' . $deviceId, 5);
    }

    public function consumeGlobal(): bool
    {
        $this->budgetCalls[] = 'global';
        ++$this->budgetConsumptions;

        if ($this->budget <= 0) {
            return false;
        }

        --$this->budget;

        return true;
    }

    public function listForPhone(PhoneNumber $phone): array
    {
        return $this->pending[$phone->toString()] ?? [];
    }

    private function consumeLayer(string $key, int $limit): bool
    {
        $this->budgetCalls[] = $key;
        $left                = $this->layerBudgets[$key] ?? $limit;

        if ($left <= 0) {
            return false;
        }

        $this->layerBudgets[$key] = $left - 1;

        return true;
    }
}
