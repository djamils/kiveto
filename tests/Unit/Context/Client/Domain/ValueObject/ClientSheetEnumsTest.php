<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\ValueObject;

use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientNoteId;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use PHPUnit\Framework\TestCase;

final class ClientSheetEnumsTest extends TestCase
{
    public function testCivilityLabelsAndHonorifics(): void
    {
        self::assertSame('Madame', Civility::MADAM->label());
        self::assertSame('Monsieur', Civility::MISTER->label());
        self::assertSame('Non précisée', Civility::UNSPECIFIED->label());
        self::assertSame('Mme', Civility::MADAM->honorific());
        self::assertSame('M.', Civility::MISTER->honorific());
        self::assertSame('', Civility::UNSPECIFIED->honorific());
    }

    public function testEveryRelationshipHasALabel(): void
    {
        self::assertSame('Conjoint', AuthorizedPersonRelationship::SPOUSE->label());
        self::assertSame('Parent', AuthorizedPersonRelationship::PARENT->label());
        self::assertSame('Enfant', AuthorizedPersonRelationship::CHILD->label());
        self::assertSame('Frère ou sœur', AuthorizedPersonRelationship::SIBLING->label());
        self::assertSame('Autre membre du foyer', AuthorizedPersonRelationship::HOUSEHOLD_MEMBER->label());
        self::assertSame('Ami ou voisin', AuthorizedPersonRelationship::FRIEND_OR_NEIGHBOUR->label());
        self::assertSame('Salarié', AuthorizedPersonRelationship::EMPLOYEE->label());
        self::assertSame('Pension ou famille d’accueil', AuthorizedPersonRelationship::BOARDING_OR_FOSTER->label());
        self::assertSame('Autre', AuthorizedPersonRelationship::OTHER->label());
    }

    public function testContactChannelLabelsAndAutomation(): void
    {
        self::assertSame('SMS', ContactChannel::SMS->label());
        self::assertSame('E-mail', ContactChannel::EMAIL->label());
        self::assertSame('Téléphone', ContactChannel::PHONE->label());
        self::assertSame('Courrier', ContactChannel::MAIL->label());

        self::assertTrue(ContactChannel::SMS->isAutomated());
        self::assertTrue(ContactChannel::EMAIL->isAutomated());
        self::assertFalse(ContactChannel::PHONE->isAutomated());
        self::assertFalse(ContactChannel::MAIL->isAutomated());
    }

    public function testEveryConsentKindHasALabel(): void
    {
        self::assertSame('Rappels de rendez-vous', ConsentKind::APPOINTMENT_REMINDERS->label());
        self::assertSame('Rappels de vaccination', ConsentKind::VACCINATION_REMINDERS->label());
        self::assertSame('Comptes rendus et ordonnances', ConsentKind::CLINICAL_DOCUMENTS->label());
        self::assertSame('Factures et relances de règlement', ConsentKind::BILLING->label());
        self::assertSame('Actualités et offres', ConsentKind::MARKETING->label());
    }

    public function testNoteKindAndDirectionLabels(): void
    {
        self::assertSame('Information', ClientNoteKind::INFORMATION->label());
        self::assertSame('Point d’attention', ClientNoteKind::ATTENTION->label());
        self::assertSame('Reçu', CommunicationDirection::INBOUND->label());
        self::assertSame('Émis', CommunicationDirection::OUTBOUND->label());
    }

    public function testIdentifiersAreBuiltFromStrings(): void
    {
        $uuid = '01234567-89ab-cdef-0123-456789abcdef';

        self::assertSame($uuid, ClientNoteId::fromString($uuid)->toString());
        self::assertSame($uuid, ClientCommunicationId::fromString($uuid)->toString());
    }
}
