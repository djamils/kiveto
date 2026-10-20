<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format;

use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;
use PHPUnit\Framework\TestCase;

final class XsdValidatorTest extends TestCase
{
    private const string MINIMAL = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
            xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
            xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
          <rsm:ExchangedDocumentContext/>
          <rsm:ExchangedDocument>
            <ram:IssueDateTime><udt:DateTimeString format="102">20260101</udt:DateTimeString></ram:IssueDateTime>
          </rsm:ExchangedDocument>
          <rsm:SupplyChainTradeTransaction>
            <ram:ApplicableHeaderTradeAgreement/>
            <ram:ApplicableHeaderTradeDelivery/>
            <ram:ApplicableHeaderTradeSettlement/>
          </rsm:SupplyChainTradeTransaction>
        </rsm:CrossIndustryInvoice>
        XML;

    public function testValidDocumentHasNoErrors(): void
    {
        self::assertSame([], new XsdValidator()->validate(self::MINIMAL, AfnorSamplesXsdTest::SCHEMA));
    }

    public function testSchemaViolationIsReportedWithLibxmlMessages(): void
    {
        $xml = str_replace('<ram:ApplicableHeaderTradeAgreement/>', '', self::MINIMAL);

        $errors = new XsdValidator()->validate($xml, AfnorSamplesXsdTest::SCHEMA);

        self::assertCount(1, $errors);
        self::assertStringContainsString('ApplicableHeaderTradeAgreement', $errors[0]);
        self::assertStringStartsWith('line ', $errors[0]);
    }

    public function testMalformedXmlIsReportedNotThrown(): void
    {
        $errors = new XsdValidator()->validate('<rsm:CrossIndustryInvoice>', AfnorSamplesXsdTest::SCHEMA);

        self::assertNotSame([], $errors);
    }

    public function testEmptyDocumentIsReported(): void
    {
        self::assertSame(['The document is not well-formed XML.'], new XsdValidator()->validate('', AfnorSamplesXsdTest::SCHEMA));
    }

    public function testCallerLibxmlStateIsRestored(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            new XsdValidator()->validate('<broken', AfnorSamplesXsdTest::SCHEMA);

            self::assertFalse(libxml_use_internal_errors(false));
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
