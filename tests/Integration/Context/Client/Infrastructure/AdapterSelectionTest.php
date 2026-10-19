<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure;

use App\Context\Client\Infrastructure\Registry\CachedCompanyRegistry;
use App\Context\Client\Infrastructure\Registry\FixtureCompanyRegistry;
use App\Context\Client\Infrastructure\Vat\FixtureVatNumberVerifier;
use App\Context\Client\Infrastructure\Vat\ViesVatNumberVerifier;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Tests and CI run on the fixture adapters; the real ones stay buildable.
 */
final class AdapterSelectionTest extends KernelTestCase
{
    public function testTheTestEnvironmentUsesTheFixtureAdapters(): void
    {
        $container = self::getContainer();

        self::assertInstanceOf(FixtureCompanyRegistry::class, $container->get('test.client.company_registry'));
        self::assertInstanceOf(FixtureVatNumberVerifier::class, $container->get('test.client.vat_verifier'));
    }

    public function testTheLocatorsOfferTheRealAdapters(): void
    {
        $registries = self::getContainer()->get('test.client.company_registry.candidates');
        $verifiers  = self::getContainer()->get('test.client.vat_verifier.candidates');
        \assert($registries instanceof ServiceLocator && $verifiers instanceof ServiceLocator);

        self::assertSame(['fixture', 'api'], array_keys($registries->getProvidedServices()));
        self::assertSame(['fixture', 'vies'], array_keys($verifiers->getProvidedServices()));
        self::assertInstanceOf(CachedCompanyRegistry::class, $registries->get('api'));
        self::assertInstanceOf(ViesVatNumberVerifier::class, $verifiers->get('vies'));
    }

    public function testTheSchedulerLockIsStillTheSharedOne(): void
    {
        $container = self::getContainer();

        self::assertSame($container->get('test.shared.lock.factory'), $container->get(LockFactory::class));
        self::assertNotSame($container->get('test.client.external_limiter.lock_factory'), $container->get(LockFactory::class));
    }

    public function testTheThreeBudgetsExist(): void
    {
        foreach (['test.client.limiter.registry_web', 'test.client.limiter.registry_worker', 'test.client.limiter.vies_worker'] as $id) {
            self::assertInstanceOf(RateLimiterFactory::class, self::getContainer()->get($id), $id);
        }
    }
}
