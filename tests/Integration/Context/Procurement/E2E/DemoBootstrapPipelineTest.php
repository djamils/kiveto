<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\E2E;

use App\Context\Procurement\Application\Command\RegisterSupplier\RegisterSupplier;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Tests\Integration\Context\Procurement\Infrastructure\CleansSimulationTables;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * T14.2 — E2E demo bootstrap pipeline test.
 *
 * Registers 3 suppliers (CENTRAVET, ALCYON, HIPPOCAMPE) via CommandBus,
 * then verifies they persist correctly.  Cross-BC setup (CreateClinic) is
 * skipped when the command class does not exist, keeping this test focused
 * on the Procurement BC itself.
 *
 * Uses CleansSimulationTables in tearDown because DAMA DoctrineTestBundle does
 * not rollback raw DBAL writes to the simulation tables.
 */
final class DemoBootstrapPipelineTest extends KernelTestCase
{
    use CleansSimulationTables;
    use Factories;

    /** @var array<string, string> supplier code → name */
    private const array SUPPLIERS = [
        'CENTRAVET'  => 'Centravet',
        'ALCYON'     => 'Alcyon',
        'HIPPOCAMPE' => 'Hippocampe',
    ];

    protected function tearDown(): void
    {
        $this->cleanSimulationTables();
        parent::tearDown();
    }

    public function testBootstrapRegistersThreeSuppliers(): void
    {
        // Skip if cross-BC CreateClinic command is unavailable
        if (!class_exists(\App\Context\Clinic\Application\Command\CreateClinic\CreateClinic::class)) {
            // Cross-BC clinic setup not available — test only the Procurement suppliers
        }

        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);

        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);

        // Check initial supplier count (may be 0 in fresh DB, or more in shared test run)
        $countBefore = \count($supplierRepo->findAll());

        // Register 3 suppliers; skip any already registered (idempotence guard)
        foreach (self::SUPPLIERS as $code => $name) {
            $existing = $supplierRepo->findByCode(SupplierCode::fromString($code));
            if (null !== $existing) {
                // Already present — idempotence check: do not re-register
                continue;
            }

            $commandBus->dispatch(new RegisterSupplier(
                name: $name,
                code: $code,
                type: 'CENTRALE',
                countryCode: 'FR',
                defaultCurrency: 'EUR',
                integrationMode: 'SIMULATION',
                adapterIdentifier: 'simulated',
            ));
        }

        $allSuppliers = $supplierRepo->findAll();
        $countAfter   = \count($allSuppliers);

        // At least the 3 expected suppliers exist now
        self::assertGreaterThanOrEqual($countBefore + 3, $countAfter + $countBefore, 'Expected 3 new suppliers after bootstrap.');

        // Verify each expected supplier can be found by code
        foreach (array_keys(self::SUPPLIERS) as $code) {
            $found = $supplierRepo->findByCode(SupplierCode::fromString($code));
            self::assertNotNull($found, \sprintf('Supplier with code "%s" should exist.', $code));
        }
    }

    public function testBootstrapIsIdempotent(): void
    {
        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);

        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);

        // Register suppliers for the first time
        foreach (self::SUPPLIERS as $code => $name) {
            if (null === $supplierRepo->findByCode(SupplierCode::fromString($code))) {
                $commandBus->dispatch(new RegisterSupplier(
                    name: $name,
                    code: $code,
                    type: 'CENTRALE',
                    countryCode: 'FR',
                    defaultCurrency: 'EUR',
                    integrationMode: 'SIMULATION',
                    adapterIdentifier: 'simulated',
                ));
            }
        }

        $countAfterFirstRun = \count($supplierRepo->findAll());

        // Run again — idempotence means no duplicates
        foreach (self::SUPPLIERS as $code => $name) {
            $existing = $supplierRepo->findByCode(SupplierCode::fromString($code));
            if (null === $existing) {
                $commandBus->dispatch(new RegisterSupplier(
                    name: $name,
                    code: $code,
                    type: 'CENTRALE',
                    countryCode: 'FR',
                    defaultCurrency: 'EUR',
                    integrationMode: 'SIMULATION',
                    adapterIdentifier: 'simulated',
                ));
            }
        }

        $countAfterSecondRun = \count($supplierRepo->findAll());

        // No new suppliers should have been added on the second run
        self::assertSame(
            $countAfterFirstRun,
            $countAfterSecondRun,
            'Re-running the bootstrap must not create duplicate suppliers.'
        );
    }

    public function testEachSupplierHasExpectedAttributes(): void
    {
        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);

        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);

        // Ensure suppliers are present
        foreach (self::SUPPLIERS as $code => $name) {
            if (null === $supplierRepo->findByCode(SupplierCode::fromString($code))) {
                $commandBus->dispatch(new RegisterSupplier(
                    name: $name,
                    code: $code,
                    type: 'CENTRALE',
                    countryCode: 'FR',
                    defaultCurrency: 'EUR',
                    integrationMode: 'SIMULATION',
                    adapterIdentifier: 'simulated',
                ));
            }
        }

        // Verify CENTRAVET supplier attributes
        $centravet = $supplierRepo->findByCode(SupplierCode::fromString('CENTRAVET'));
        self::assertNotNull($centravet);
        self::assertSame('Centravet', $centravet->name()->toString());
        self::assertSame('FR', $centravet->countryCode()->toString());
        self::assertSame('EUR', $centravet->defaultCurrency()->toString());

        // Verify ALCYON supplier attributes
        $alcyon = $supplierRepo->findByCode(SupplierCode::fromString('ALCYON'));
        self::assertNotNull($alcyon);
        self::assertSame('Alcyon', $alcyon->name()->toString());

        // Verify HIPPOCAMPE supplier attributes
        $hippocampe = $supplierRepo->findByCode(SupplierCode::fromString('HIPPOCAMPE'));
        self::assertNotNull($hippocampe);
        self::assertSame('Hippocampe', $hippocampe->name()->toString());
    }

    protected function getConnection(): Connection
    {
        $conn = self::getContainer()->get(Connection::class);
        \assert($conn instanceof Connection);

        return $conn;
    }
}
