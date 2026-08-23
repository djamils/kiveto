<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing;

use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings\UpdateClinicBillingSettings;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The prefix a clinic chose has to reach the invoices it issues.
 */
final class InvoiceNumberPrefixTest extends KernelTestCase
{
    use Factories;

    private InvoiceNumberGeneratorInterface $numbers;
    private CommandBusInterface $commandBus;
    private string $clinicId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $numbers = $container->get(InvoiceNumberGeneratorInterface::class);
        \assert($numbers instanceof InvoiceNumberGeneratorInterface);
        $this->numbers = $numbers;

        $commandBus = $container->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $this->clinicId = ClinicEntityFactory::createOne()->getId()->toRfc4122();
    }

    public function testAClinicWithoutAPrefixKeepsTheDefaultOne(): void
    {
        $number = $this->numbers->next(ClinicId::fromString($this->clinicId), 2026);

        self::assertStringStartsWith('FAC-2026-', $number->toString());
    }

    public function testTheInvoiceCarriesThePrefixTheClinicChose(): void
    {
        $this->commandBus->dispatch(new UpdateClinicBillingSettings(
            clinicId: $this->clinicId,
            invoiceNumberPrefix: 'lilas',
        ));

        $number = $this->numbers->next(ClinicId::fromString($this->clinicId), 2026);

        self::assertSame('LILAS-2026-000001', $number->toString());
    }

    public function testTheCounterKeepsRunningWhenThePrefixChanges(): void
    {
        $clinic = ClinicId::fromString($this->clinicId);

        $this->numbers->next($clinic, 2026);

        $this->commandBus->dispatch(new UpdateClinicBillingSettings(
            clinicId: $this->clinicId,
            invoiceNumberPrefix: 'F/2026',
        ));

        $second = $this->numbers->next($clinic, 2026);

        // Renaming the series does not restart it: the sequence is the clinic's,
        // not the prefix's.
        self::assertSame('F/2026-2026-000002', $second->toString());
    }
}
