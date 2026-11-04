<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform;

use App\Shared\Infrastructure\Deployment\Deployment;
use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Disabled\DisabledPlatform;
use App\System\ElectronicInvoicing\Infrastructure\Platform\PlatformSelector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PlatformSelectorTest extends TestCase
{
    public function testTheConfiguredAdapterServesEveryPort(): void
    {
        $selector = $this->selector('disabled', Deployment::PRODUCTION);

        self::assertNull($selector->code());
        self::assertInstanceOf(DisabledPlatform::class, $selector->submitter());
        self::assertInstanceOf(DisabledPlatform::class, $selector->searcher());
        self::assertInstanceOf(DisabledPlatform::class, $selector->fetcher());
        self::assertInstanceOf(DisabledPlatform::class, $selector->directory());
        self::assertSame('simulator', $this->selector('simulator', Deployment::DEV)->code());
        // STAGING and DEMO run APP_ENV=prod with the simulator.
        self::assertSame('simulator', $this->selector('simulator', Deployment::STAGING)->code());
        self::assertSame('simulator', $this->selector('simulator', Deployment::DEMO)->code());
    }

    /**
     * AC 31: checked when the port is resolved, with the deployment of the run.
     */
    public function testTheSimulatorIsRefusedInProduction(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The electronic-invoicing simulator cannot run in production.');

        $this->selector('simulator', Deployment::PRODUCTION)->submitter();
    }

    public function testAnUnknownPlatformIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown electronic-invoicing platform "acme".');

        $this->selector('acme', Deployment::DEV)->code();
    }

    public function testTheDisabledPlatformIsNeverReachable(): void
    {
        $platform = new DisabledPlatform();
        $calls    = [
            static fn () => $platform->submit(new OutboundFlow('o', 't', 'f', 'c', 's', 'CII', 'B2B')),
            static fn () => $platform->search('o', new \DateTimeImmutable(), '', 1),
            static fn () => $platform->fetch('o', 'f'),
            static fn () => $platform->lookup('s'),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                self::fail('Expected the disabled platform to refuse.');
            } catch (\LogicException $e) {
                self::assertSame('Electronic invoicing is disabled.', $e->getMessage());
            }
        }
    }

    private function selector(string $platform, Deployment $deployment): PlatformSelector
    {
        $adapter = new DisabledPlatform();

        return new PlatformSelector(
            new ServiceLocator(['disabled' => static fn (): DisabledPlatform => $adapter, 'simulator' => static fn (): DisabledPlatform => $adapter]),
            $platform,
            $deployment,
        );
    }
}
