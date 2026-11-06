<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Service;

use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\SecondFactorSecretsInterface;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;

/**
 * Ten fresh backup codes, and the digest a typed one is compared with:
 * case, spaces and dashes do not count.
 */
final readonly class BackupCodes
{
    public function __construct(
        private SecondFactorSecretsInterface $secrets,
        private OtpCodeHasherInterface $hasher,
    ) {
    }

    /**
     * @return array{plain: list<string>, hashes: list<string>}
     */
    public function fresh(): array
    {
        $plain = [];
        for ($draw = 0; \count($plain) < SecondFactor::BACKUP_CODE_COUNT; ++$draw) {
            if ($draw >= 100) {
                throw new \RuntimeException('The backup code source keeps repeating itself.');
            }

            $code = $this->secrets->newBackupCode();
            // A repeat is drawn again: the ten codes are distinct.
            $plain[$this->normalised($code)] = $code;
        }

        $plain = array_values($plain);

        return ['plain' => $plain, 'hashes' => array_map($this->hash(...), $plain)];
    }

    public function hash(string $typed): string
    {
        return $this->hasher->hash('backup:' . $this->normalised($typed));
    }

    private function normalised(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', $code));
    }
}
