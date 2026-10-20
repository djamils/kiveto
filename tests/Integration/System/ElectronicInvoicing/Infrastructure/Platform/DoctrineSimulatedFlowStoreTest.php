<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\ElectronicInvoicing\Infrastructure\Platform;

use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\DepositedDocument;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlowStoreInterface;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedStatus;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScenario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineSimulatedFlowStoreTest extends KernelTestCase
{
    private const string CII = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
          <rsm:ExchangedDocument>
            <ram:ID>PAR-2026-000001</ram:ID>
            <ram:TypeCode>381</ram:TypeCode>
            <ram:IssueDateTime><udt:DateTimeString format="102">20261021</udt:DateTimeString></ram:IssueDateTime>
          </rsm:ExchangedDocument>
          <rsm:SupplyChainTradeTransaction>
            <ram:ApplicableHeaderTradeAgreement>
              <ram:SellerTradeParty><ram:SpecifiedLegalOrganization><ram:ID schemeID="0002">812456903</ram:ID></ram:SpecifiedLegalOrganization></ram:SellerTradeParty>
              <ram:BuyerTradeParty><ram:SpecifiedLegalOrganization><ram:ID schemeID="0002">000002105</ram:ID></ram:SpecifiedLegalOrganization></ram:BuyerTradeParty>
            </ram:ApplicableHeaderTradeAgreement>
          </rsm:SupplyChainTradeTransaction>
        </rsm:CrossIndustryInvoice>
        XML;

    private SimulatedFlowStoreInterface $store;

    protected function setUp(): void
    {
        self::bootKernel();
        $store = self::getContainer()->get(SimulatedFlowStoreInterface::class);
        \assert($store instanceof SimulatedFlowStoreInterface);
        $this->store = $store;
    }

    public function testSavesAndReconstitutesEveryProperty(): void
    {
        $flow = $this->deposit('flow-a', 'tracking-a', SimulationScenario::REFUSED, '2026-10-21T10:00:00+00:00');
        $flow->advance(new \DateTimeImmutable('2026-10-21T10:00:30+00:00'));
        $this->store->save($flow);
        $this->clear();

        $found = $this->store->find('flow-a');

        self::assertNotNull($found);
        self::assertSame('flow-a', $found->flowId);
        self::assertSame('812456903', $found->organisationId);
        self::assertSame('tracking-a', $found->trackingId);
        self::assertSame(hash('sha256', self::CII . 'tracking-a'), $found->sha256);
        self::assertSame('PAR-2026-000001', $found->documentNumber);
        self::assertSame('381', $found->typeCode);
        self::assertSame('2026-10-21', $found->issueDate?->format('Y-m-d'));
        self::assertSame('812456903', $found->sellerSiren);
        self::assertSame('000002105', $found->buyerSiren);
        self::assertSame(SimulationScenario::REFUSED, $found->scenario);
        self::assertSame(self::CII . 'tracking-a', $found->file);
        self::assertSame('2026-10-21T10:00:00+00:00', $found->createdAt->format(\DATE_ATOM));
        self::assertSame('2026-10-21T10:00:30+00:00', $found->updatedAt()->format(\DATE_ATOM));
        self::assertSame(2, $found->nextStep());
        self::assertSame(
            [[200, null, '2026-10-21T10:00:00+00:00'], [202, null, '2026-10-21T10:00:30+00:00']],
            array_map(static fn (SimulatedStatus $s): array => [$s->code, $s->reason, $s->at->format(\DATE_ATOM)], $found->statuses()),
        );
        self::assertNull($this->store->find('unknown'));
    }

    public function testFindsByDepositByDocumentByOrganisationAndPending(): void
    {
        $older = $this->deposit('flow-a', 'tracking-a', SimulationScenario::NOMINAL, '2026-10-21T10:00:00+00:00');
        $newer = $this->deposit('flow-b', 'tracking-b', SimulationScenario::NOT_TRANSMITTED, '2026-10-21T11:00:00+00:00');
        $other = $this->deposit('flow-c', 'tracking-c', SimulationScenario::NOMINAL, '2026-10-21T09:00:00+00:00', '999999999');
        $this->clear();

        self::assertSame('flow-a', $this->store->findByDeposit('tracking-a', $older->sha256)?->flowId);
        self::assertNull($this->store->findByDeposit('tracking-a', $newer->sha256));
        self::assertSame('flow-b', $this->store->findByDocument('812456903', 'PAR-2026-000001')?->flowId);
        self::assertNull($this->store->findByDocument('812456903', 'NOPE'));
        self::assertSame(['flow-a', 'flow-b'], array_map(static fn (SimulatedFlow $f): string => $f->flowId, $this->store->forOrganisation('812456903')));
        self::assertSame(['flow-c', 'flow-a'], array_map(static fn (SimulatedFlow $f): string => $f->flowId, $this->store->pending()));
        self::assertSame('flow-c', $other->flowId);
    }

    private function deposit(string $flowId, string $trackingId, SimulationScenario $scenario, string $now, string $organisation = '812456903'): SimulatedFlow
    {
        $content = self::CII . $trackingId;
        $flow    = SimulatedFlow::deposit(
            $flowId,
            new OutboundFlow($organisation, $trackingId, $trackingId . '.xml', $content, hash('sha256', $content), 'CII', 'B2B'),
            DepositedDocument::read(self::CII),
            $scenario,
            new \DateTimeImmutable($now),
        );
        $this->store->save($flow);

        return $flow;
    }

    private function clear(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->clear();
    }
}
