<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Infrastructure\Adapter;

use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation\PrescribedLine;
use App\Context\Prescription\Infrastructure\Adapter\Catalog\QueryBusDrugRegistry;
use App\Context\Prescription\Infrastructure\Adapter\Consultation\QueryBusConsultationPrescriptionProvider;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\MarketingAuthorizationDetailView;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class PrescriptionAdaptersTest extends TestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000801';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-000000000802';
    private const string ARTICLE_ID      = '01960000-0000-7000-8000-000000000803';
    private const string AUTH_ID         = '01960000-0000-7000-8000-000000000804';

    // ── Consultation lines ──

    public function testTheConsultationLinesAreMappedAcrossTheBoundary(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->expects(self::once())->method('ask')->willReturn([
            new PrescribedLine('l1', self::ARTICLE_ID, 'MELOXIDYL-1', 'Meloxidyl', '0,1 mg/kg', '1×/j', 7, 'per os', '1'),
            'not a line',
        ]);

        $lines = (new QueryBusConsultationPrescriptionProvider($queryBus))
            ->linesForConsultation(self::CONSULTATION_ID, self::CLINIC_ID)
        ;

        // The stray value is dropped rather than breaking the ordonnance.
        self::assertCount(1, $lines);
        self::assertSame('Meloxidyl', $lines[0]->label);
        self::assertSame('MELOXIDYL-1', $lines[0]->code);
        self::assertSame(self::ARTICLE_ID, $lines[0]->articleId);
        self::assertSame('l1', $lines[0]->sourceLineId);
        self::assertSame('0,1 mg/kg', $lines[0]->dose);
        self::assertSame('1×/j', $lines[0]->frequency);
        self::assertSame(7, $lines[0]->durationDays);
        self::assertSame('per os', $lines[0]->route);
        self::assertSame('1', $lines[0]->quantity);
    }

    public function testAnUnexpectedAnswerYieldsNoLines(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn('nothing useful');

        self::assertSame(
            [],
            (new QueryBusConsultationPrescriptionProvider($queryBus))
                ->linesForConsultation(self::CONSULTATION_ID, self::CLINIC_ID),
        );
    }

    // ── Drug registry ──

    public function testTheMedicineIsDescribedFromTheCatalogAndTheRegistry(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            fn (object $query): object => $query instanceof GetArticleDetail
                ? $this->article(self::AUTH_ID, 'RX_NARCOTIC')
                : $this->authorization(['Kétamine', '  ', 'Autre']),
        );

        $details = (new QueryBusDrugRegistry($queryBus))->detailsOf(self::ARTICLE_ID, self::CLINIC_ID);

        self::assertSame('Solution for injection', $details->pharmaceuticalForm);
        self::assertSame(['Kétamine', 'Autre'], $details->activeSubstances);
        self::assertSame('RX_NARCOTIC', $details->prescriptionClass);
    }

    public function testAnArticleWithoutAnAuthorizationKeepsItsClassification(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn($this->article(null, 'RX'));

        $details = (new QueryBusDrugRegistry($queryBus))->detailsOf(self::ARTICLE_ID, self::CLINIC_ID);

        self::assertNull($details->pharmaceuticalForm);
        self::assertSame([], $details->activeSubstances);
        self::assertSame('RX', $details->prescriptionClass);
    }

    public function testAnArticleWithoutAClassificationIsPlain(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn($this->article(null, null));

        self::assertSame(
            'NONE',
            (new QueryBusDrugRegistry($queryBus))->detailsOf(self::ARTICLE_ID, self::CLINIC_ID)->prescriptionClass,
        );
    }

    public function testAnUnknownArticleDescribesNothing(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(new \RuntimeException('no such article'));

        $details = (new QueryBusDrugRegistry($queryBus))->detailsOf(self::ARTICLE_ID, self::CLINIC_ID);

        self::assertSame('NONE', $details->prescriptionClass);
        self::assertSame([], $details->activeSubstances);
    }

    public function testAnUnexpectedArticleAnswerDescribesNothing(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn('not an article');

        self::assertSame(
            'NONE',
            (new QueryBusDrugRegistry($queryBus))->detailsOf(self::ARTICLE_ID, self::CLINIC_ID)->prescriptionClass,
        );
    }

    public function testAWithdrawnAuthorizationLeavesTheLineAsTyped(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query): object {
                if ($query instanceof GetArticleDetail) {
                    return $this->article(self::AUTH_ID, 'RX');
                }

                throw new \RuntimeException('authorization withdrawn');
            },
        );

        $details = (new QueryBusDrugRegistry($queryBus))->detailsOf(self::ARTICLE_ID, self::CLINIC_ID);

        self::assertNull($details->pharmaceuticalForm);
        self::assertSame([], $details->activeSubstances);
        // The clinic's own classification still stands.
        self::assertSame('RX', $details->prescriptionClass);
    }

    public function testAnUnexpectedAuthorizationAnswerLeavesTheLineAsTyped(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            fn (object $query): mixed => $query instanceof GetArticleDetail
                ? $this->article(self::AUTH_ID, 'RX')
                : 'not an authorization',
        );

        self::assertNull(
            (new QueryBusDrugRegistry($queryBus))->detailsOf(self::ARTICLE_ID, self::CLINIC_ID)->pharmaceuticalForm,
        );
    }

    private function article(?string $authorizationRef, ?string $prescriptionClass): ArticleDetailView
    {
        return new ArticleDetailView(
            id: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            name: 'Ketamine 100 mg/mL',
            code: 'KETAMINE-100',
            kind: 'DRUG',
            gtin: null,
            taxCategoryCode: 'veterinary.drug.prescription',
            basePriceMinorUnits: 5600,
            basePriceCurrency: 'EUR',
            unitOfMeasure: 'VIAL',
            trackStock: true,
            status: 'ACTIVE',
            authorizationRef: $authorizationRef,
            requiresPrescription: true,
            prescriptionClass: $prescriptionClass,
            isControlledSubstance: true,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
        );
    }

    /**
     * @param list<string> $substances
     */
    private function authorization(array $substances): MarketingAuthorizationDetailView
    {
        return new MarketingAuthorizationDetailView(
            id: self::AUTH_ID,
            commercialName: 'Ketamine 100 mg/mL',
            holderLaboratory: 'Labo',
            status: 'ACTIVE',
            authorizationDate: '2014-01-01',
            nature: 'PHARMACEUTICAL',
            pharmaceuticalForm: 'Solution for injection',
            atcVetCode: null,
            permanentIdentifier: null,
            controlledSubstanceClass: 'NARCOTIC_I',
            presentations: [],
            activeSubstances: $substances,
            targetUsages: [],
            jurisdictionalIdentifiers: [],
            lastImportSource: 'seed',
            lastImportedAt: new \DateTimeImmutable('2026-01-01'),
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-01'),
        );
    }
}
