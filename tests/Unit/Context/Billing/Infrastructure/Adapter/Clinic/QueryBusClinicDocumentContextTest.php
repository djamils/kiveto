<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\Clinic;

use App\Context\Billing\Infrastructure\Adapter\Clinic\QueryBusClinicDocumentContext;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBusClinicDocumentContext::class)]
final class QueryBusClinicDocumentContextTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000001';

    public function testTheClinicAndItsPaymentDetailsAreReadFromTheClinicContext(): void
    {
        $bus = new AnsweringQueryBus([
            GetClinic::class         => self::clinic(),
            GetClinicSettings::class => self::settings([
                'iban'              => 'FR7630006000011234567890189',
                'bic'               => 'AGRIFRPP',
                'vatOnDebits'       => true,
                'invoiceFooterNote' => 'Règlement à réception.',
            ]),
        ]);

        $context = new QueryBusClinicDocumentContext($bus)->forClinic(self::CLINIC_ID);

        self::assertSame('Clinique Montmartre', $context->clinic?->name);
        self::assertSame('FR76 3000 6000 0112 3456 7890 189', $context->payment?->iban);
        self::assertSame('AGRIFRPP', $context->payment->bic);
        self::assertTrue($context->payment->vatOnDebits);
        self::assertSame('Règlement à réception.', $context->payment->invoiceFooterNote);
    }

    public function testSettingsWithoutPaymentDetailsPrintNone(): void
    {
        $bus = new AnsweringQueryBus([GetClinic::class => self::clinic(), GetClinicSettings::class => self::settings([])]);

        $payment = new QueryBusClinicDocumentContext($bus)->forClinic(self::CLINIC_ID)->payment;

        self::assertNotNull($payment);
        self::assertNull($payment->iban);
        self::assertNull($payment->bic);
        self::assertFalse($payment->vatOnDebits);
        self::assertNull($payment->invoiceFooterNote);
    }

    public function testAnUnknownClinicPrintsNeitherLetterheadNorPaymentDetails(): void
    {
        $context = new QueryBusClinicDocumentContext(new AnsweringQueryBus())->forClinic(self::CLINIC_ID);

        self::assertNull($context->clinic);
        self::assertNull($context->payment);
    }

    private static function clinic(): ClinicDto
    {
        return new ClinicDto(
            id: self::CLINIC_ID,
            name: 'Clinique Montmartre',
            slug: 'clinique-montmartre',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: 'FR',
            jurisdictionCode: null,
            currencyCode: 'EUR',
            status: ClinicStatus::ACTIVE,
            clinicGroupId: null,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
        );
    }

    /**
     * @param array<string, string|int|bool|list<string>|null> $billing
     */
    private static function settings(array $billing): ClinicSettingsView
    {
        return new ClinicSettingsView(
            id: self::CLINIC_ID,
            name: 'Clinique Montmartre',
            slug: 'clinique-montmartre',
            status: 'active',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: 'FR',
            currencyCode: 'EUR',
            letterhead: [],
            week: [],
            closures: [],
            billing: $billing,
        );
    }
}
