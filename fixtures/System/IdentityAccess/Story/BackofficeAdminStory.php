<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Story;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Deployment\Deployment;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Service\BackupCodes;
use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Zenstruck\Foundry\Story;

/**
 * The console's admin: admin@kiveto.local / admin in dev, « Djamil
 * Soidiki » in the log.
 *
 * In dev only, his second factor is already configured with the well-known
 * key `JBSWY3DPEHPK3PXP` (add it once to an authenticator app, or compute a
 * code with `oathtool --totp -b JBSWY3DPEHPK3PXP`). On every other
 * deployment the admin configures his own app at his first sign-in.
 */
final class BackofficeAdminStory extends Story
{
    public const string DEV_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public function __construct(
        private readonly BackofficeProfileStoreInterface $profiles,
        private readonly SecondFactorRepositoryInterface $secondFactors,
        private readonly BackupCodes $backupCodes,
        private readonly ClockInterface $clock,
        #[Autowire(env: 'enum:App\Shared\Infrastructure\Deployment\Deployment:KIVETO_DEPLOYMENT')]
        private readonly Deployment $deployment,
    ) {
    }

    public function build(): void
    {
        $admin = BackofficeUserFactory::new()
            ->withEmail('admin@kiveto.local')
            ->withPlainPassword('admin')
            ->create()
        ;
        $id  = $admin->getId()->toRfc4122();
        $now = $this->clock->now();

        $this->profiles->save($id, 'Djamil Soidiki', 'Europe/Paris', true, true, $now);

        if (Deployment::DEV !== $this->deployment) {
            return;
        }

        $this->secondFactors->save(SecondFactor::reconstitute(
            UserId::fromString($id),
            TotpSecret::fromBase32(self::DEV_TOTP_SECRET),
            $now,
            null,
            array_map(BackupCode::unused(...), $this->backupCodes->fresh()['hashes']),
        ));
    }
}
