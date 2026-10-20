<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform;

use App\System\ElectronicInvoicing\Application\Port\ConfiguredPlatformInterface;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryLookupInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowFetcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSearcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSubmitterInterface;
use Psr\Container\ContainerInterface;

/**
 * Picks the platform adapter named by ELECTRONIC_INVOICING_PLATFORM when a
 * port is first used — at runtime, so a value set after the image was built
 * is still checked: the local simulator never runs in production.
 */
final readonly class PlatformSelector implements ConfiguredPlatformInterface
{
    public const string DISABLED = 'disabled';

    public const string SIMULATOR = 'simulator';

    public function __construct(
        private ContainerInterface $candidates,
        private string $platform,
        private string $environment,
    ) {
    }

    public function code(): ?string
    {
        $this->guard();

        return self::DISABLED === $this->platform ? null : $this->platform;
    }

    public function submitter(): FlowSubmitterInterface
    {
        $adapter = $this->adapter();
        \assert($adapter instanceof FlowSubmitterInterface);

        return $adapter;
    }

    public function searcher(): FlowSearcherInterface
    {
        $adapter = $this->adapter();
        \assert($adapter instanceof FlowSearcherInterface);

        return $adapter;
    }

    public function fetcher(): FlowFetcherInterface
    {
        $adapter = $this->adapter();
        \assert($adapter instanceof FlowFetcherInterface);

        return $adapter;
    }

    public function directory(): DirectoryLookupInterface
    {
        $adapter = $this->adapter();
        \assert($adapter instanceof DirectoryLookupInterface);

        return $adapter;
    }

    private function adapter(): object
    {
        $this->guard();

        $adapter = $this->candidates->get($this->platform);
        \assert(\is_object($adapter));

        return $adapter;
    }

    private function guard(): void
    {
        if (self::SIMULATOR === $this->platform && 'prod' === $this->environment) {
            throw new \LogicException('The electronic-invoicing simulator cannot run in production.');
        }

        if (!$this->candidates->has($this->platform)) {
            throw new \LogicException(\sprintf('Unknown electronic-invoicing platform "%s".', $this->platform));
        }
    }
}
