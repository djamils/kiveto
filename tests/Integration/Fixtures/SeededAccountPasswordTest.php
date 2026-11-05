<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixtures;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Infrastructure\Deployment\Deployment;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * STAGING and DEMO are public: the accounts kiveto-seed creates there never
 * get the trivial passwords of the stories (vet/vet, admin/admin…).
 */
final class SeededAccountPasswordTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_SEED     = 'clinic-seed-password-0123';
    private const string BACKOFFICE_SEED = 'backoffice-seed-password-0123';

    public function testWorkstationsKeepThePasswordOfTheStory(): void
    {
        $user = $this->clinicFactory(Deployment::DEV)->withPlainPassword('vet')->withoutPersisting()->create();

        self::assertTrue($this->hasher()->isPasswordValid($user, 'vet'));
    }

    public function testClinicAccountsGetTheClinicSeedPasswordOnStaging(): void
    {
        $user = $this->clinicFactory(Deployment::STAGING)->withPlainPassword('vet')->withoutPersisting()->create();

        self::assertTrue($this->hasher()->isPasswordValid($user, self::CLINIC_SEED));
        self::assertFalse($this->hasher()->isPasswordValid($user, 'vet'));
    }

    public function testBackofficeAccountsGetTheirOwnSeedPasswordOnDemo(): void
    {
        $user = $this->backofficeFactory(Deployment::DEMO)->withPlainPassword('admin')->withoutPersisting()->create();

        self::assertTrue($this->hasher()->isPasswordValid($user, self::BACKOFFICE_SEED));
        self::assertFalse($this->hasher()->isPasswordValid($user, self::CLINIC_SEED));
        self::assertFalse($this->hasher()->isPasswordValid($user, 'admin'));
    }

    public function testSeedingOutsideDevWithoutTheSeedPasswordIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('KIVETO_SEED_PASSWORD must hold at least 16 characters to seed accounts on the staging deployment.');

        $this->clinicFactory(Deployment::STAGING, clinicSeed: null)->withPlainPassword('vet');
    }

    public function testATooShortBackofficeSeedPasswordIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('KIVETO_SEED_BACKOFFICE_PASSWORD must hold at least 16 characters to seed accounts on the demo deployment.');

        $this->backofficeFactory(Deployment::DEMO, backofficeSeed: 'admin-but-short')->withPlainPassword('admin');
    }

    private function clinicFactory(Deployment $deployment, ?string $clinicSeed = self::CLINIC_SEED): ClinicUserFactory
    {
        return new ClinicUserFactory($this->hasher(), $deployment, $clinicSeed, self::BACKOFFICE_SEED);
    }

    private function backofficeFactory(Deployment $deployment, ?string $backofficeSeed = self::BACKOFFICE_SEED): BackofficeUserFactory
    {
        return new BackofficeUserFactory($this->hasher(), $deployment, self::CLINIC_SEED, $backofficeSeed);
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        return $hasher;
    }
}
