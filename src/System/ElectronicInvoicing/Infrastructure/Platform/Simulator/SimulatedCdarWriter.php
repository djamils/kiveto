<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;

/**
 * Writes the lifecycle message (CDAR « CDV », AFNOR sample structure) the
 * simulated platform publishes for one status of a deposited document.
 *
 * Simulator only: this is not the CDAR renderer Kiveto will need to send its
 * own statuses (T3, which requires the UN/CEFACT CDAR XSD).
 */
final readonly class SimulatedCdarWriter
{
    private const string NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100';
    private const string NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    private const string NS_QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';
    private const string NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private const array REASONS = [
        'REJ_SEMAN'     => 'Rejet pour erreur sémantique',
        'DOUBLON'       => 'Facture en doublon (déjà émise / reçue)',
        'TX_TVA_ERR'    => 'Taux de TVA erroné',
        'NON_TRANSMISE' => 'Facture non transmise au destinataire',
        'IRR_SYNTAX'    => 'Irrecevable : erreur de syntaxe',
    ];

    public function write(SimulatedFlow $flow, SimulatedStatus $status): string
    {
        // CDAR date-times (format 204) carry no offset: French local time.
        $at   = $status->at->setTimezone(new \DateTimeZone('Europe/Paris'))->format('YmdHis');
        $code = LifecycleStatusCode::tryFrom($status->code);

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('rsm:CrossDomainAcknowledgementAndResponse');
        $writer->writeAttribute('xmlns:rsm', self::NS_RSM);
        $writer->writeAttribute('xmlns:ram', self::NS_RAM);
        $writer->writeAttribute('xmlns:qdt', self::NS_QDT);
        $writer->writeAttribute('xmlns:udt', self::NS_UDT);

        $writer->startElement('rsm:ExchangedDocumentContext');
        $writer->startElement('ram:BusinessProcessSpecifiedDocumentContextParameter');
        $writer->writeElement('ram:ID', 'REGULATED');
        $writer->endElement();
        $writer->startElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $writer->writeElement('ram:ID', 'urn.cpro.gouv.fr:1p0:CDV:invoice');
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('rsm:ExchangedDocument');
        $writer->writeElement('ram:ID', \sprintf('%s_%d_%s#%s', $flow->documentNumber, $status->code, $at, $flow->typeCode));
        $this->dateTime($writer, 'ram:IssueDateTime', 'udt:DateTimeString', '204', $at);
        $writer->startElement('ram:SenderTradeParty');
        $writer->writeElement('ram:RoleCode', 'WK');
        $writer->endElement();
        $writer->startElement('ram:RecipientTradeParty');
        $this->globalId($writer, $flow->sellerSiren);
        $writer->writeElement('ram:RoleCode', 'SE');
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('rsm:AcknowledgementDocument');
        $writer->startElement('ram:MultipleReferencesIndicator');
        $writer->writeElement('udt:Indicator', 'false');
        $writer->endElement();
        $writer->writeElement('ram:TypeCode', '23');
        $this->dateTime($writer, 'ram:IssueDateTime', 'udt:DateTimeString', '204', $at);

        $writer->startElement('ram:ReferenceReferencedDocument');
        $writer->writeElement('ram:IssuerAssignedID', $flow->documentNumber);
        $writer->writeElement('ram:TypeCode', $flow->typeCode);
        if (null !== $flow->issueDate) {
            $this->dateTime($writer, 'ram:FormattedIssueDateTime', 'qdt:DateTimeString', '102', $flow->issueDate->format('Ymd'));
        }
        $writer->writeElement('ram:ProcessConditionCode', (string) $status->code);
        if (null !== $code) {
            $writer->writeElement('ram:ProcessCondition', $code->label());
        }
        $writer->startElement('ram:IssuerTradeParty');
        $this->globalId($writer, $flow->sellerSiren);
        $writer->endElement();
        if (null !== $status->reason) {
            $writer->startElement('ram:SpecifiedDocumentStatus');
            $writer->writeElement('ram:ReasonCode', $status->reason);
            $writer->writeElement('ram:Reason', self::REASONS[$status->reason] ?? $status->reason);
            $writer->endElement();
        }
        $writer->endElement();

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function dateTime(\XMLWriter $writer, string $name, string $stringElement, string $format, string $value): void
    {
        $writer->startElement($name);
        $writer->startElement($stringElement);
        $writer->writeAttribute('format', $format);
        $writer->text($value);
        $writer->endElement();
        $writer->endElement();
    }

    private function globalId(\XMLWriter $writer, string $siren): void
    {
        $writer->startElement('ram:GlobalID');
        $writer->writeAttribute('schemeID', '0002');
        $writer->text($siren);
        $writer->endElement();
    }
}
