<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Directory;

use App\Presentation\Clinic\ViewModel\Directory\DirectoryClientInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectoryClientInputTest extends TestCase
{
    private const array OK = ['civ' => 'Mme', 'nom' => 'LE GALLOU', 'pre' => 'anne-marie', 'mob' => '06 98 76 54 12'];

    public function testNormalisesTheClientAsTheRecordKeepsIt(): void
    {
        $client = DirectoryClientInput::parse(self::OK + [
            'fixe' => '01 42 00 00 00', 'mail' => ' Anne.LG@Exemple.FR ',
            'rue'  => '12 Rue de Rome', 'cpl' => 'Bât. B', 'cp' => '75008', 'ville' => 'Paris',
            'rap'  => 'sms', 'rsms' => true, 'news' => true, 'autre' => true,
        ]);

        self::assertInstanceOf(DirectoryClientInput::class, $client);
        self::assertSame('madam', $client->civility);
        self::assertSame('Le Gallou', $client->lastName);
        self::assertSame('Anne-Marie', $client->firstName);
        self::assertSame('Mme Anne-Marie Le Gallou', $client->title());
        self::assertSame(['+33698765412', '+33142000000'], $client->phones());
        self::assertTrue($client->household);

        $methods = $client->contactMethods();
        self::assertCount(3, $methods);
        self::assertSame(['mobile', true], [$methods[0]->label, $methods[0]->isPrimary]);
        self::assertSame(['home', false], [$methods[1]->label, $methods[1]->isPrimary]);
        self::assertSame(['email', 'anne.lg@exemple.fr'], [$methods[2]->type, $methods[2]->value]);

        $address = $client->postalAddress();
        self::assertNotNull($address);
        self::assertSame(['12 Rue de Rome', 'Bât. B', '75008', 'Paris', 'FR'], [$address->streetLine1, $address->streetLine2, $address->postalCode, $address->city, $address->countryCode]);

        self::assertSame('sms', $client->preferredChannel());
        self::assertSame([
            'appointment_reminders' => ['sms'],
            'vaccination_reminders' => ['sms'],
            'marketing'             => ['sms', 'email'],
        ], $client->consents());
    }

    public function testALandlineAloneIsTheMainNumberAndRemindsNobody(): void
    {
        $client = DirectoryClientInput::parse(['civ' => 'M.', 'nom' => 'MARTIN', 'pre' => 'paul', 'fixe' => '0142000000', 'rap' => 'sms', 'rsms' => true, 'news' => true]);

        self::assertInstanceOf(DirectoryClientInput::class, $client);
        self::assertSame('M. Paul Martin', $client->title());
        self::assertTrue($client->contactMethods()[0]->isPrimary);
        self::assertNull($client->postalAddress());
        self::assertNull($client->preferredChannel());
        self::assertFalse($client->smsReminders, 'no SMS reminder without a mobile');
        self::assertSame([], $client->consents());
    }

    public function testEmailReminders(): void
    {
        $client = DirectoryClientInput::parse(self::OK + ['mail' => 'a@b.fr', 'rap' => 'mail']);

        self::assertInstanceOf(DirectoryClientInput::class, $client);
        self::assertSame('email', $client->preferredChannel());
        self::assertSame(['appointment_reminders' => ['email'], 'vaccination_reminders' => ['email']], $client->consents());

        $both = DirectoryClientInput::parse(self::OK + ['mail' => 'a@b.fr', 'rap' => 'mail', 'rsms' => true]);
        self::assertInstanceOf(DirectoryClientInput::class, $both);
        self::assertSame(['appointment_reminders' => ['sms', 'email'], 'vaccination_reminders' => ['email']], $both->consents());
    }

    /** D9: the SMS appointment reminder follows its own box, ticked by default with a mobile. */
    public function testSmsAppointmentRemindersFollowTheirOwnBox(): void
    {
        $unticked = DirectoryClientInput::parse(self::OK + ['rap' => 'sms', 'rsms' => false]);
        self::assertInstanceOf(DirectoryClientInput::class, $unticked);
        self::assertFalse($unticked->smsReminders);
        self::assertSame(['vaccination_reminders' => ['sms']], $unticked->consents());

        $ticked = DirectoryClientInput::parse(self::OK + ['rap' => 'aucun', 'rsms' => true]);
        self::assertInstanceOf(DirectoryClientInput::class, $ticked);
        self::assertTrue($ticked->smsReminders);
        self::assertNull($ticked->preferredChannel());
        self::assertSame(['appointment_reminders' => ['sms']], $ticked->consents());
    }

    /**
     * @param array<string, string> $raw
     */
    #[DataProvider('provideSaysTheFirstThingMissingCases')]
    public function testSaysTheFirstThingMissing(array $raw, string $message): void
    {
        self::assertSame($message, DirectoryClientInput::parse($raw));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideSaysTheFirstThingMissingCases(): iterable
    {
        yield 'civility' => [['nom' => 'MARTIN'], 'Civilité requise'];
        yield 'last name' => [['civ' => 'M.', 'nom' => 'M'], 'Nom requis'];
        yield 'first name' => [['civ' => 'M.', 'nom' => 'MARTIN', 'pre' => ''], 'Prénom requis'];
        yield 'no phone' => [['civ' => 'M.', 'nom' => 'MARTIN', 'pre' => 'Paul'], 'Un téléphone au moins'];
        yield 'bad phone' => [['civ' => 'M.', 'nom' => 'MARTIN', 'pre' => 'Paul', 'mob' => '0812'], 'Téléphone invalide'];
        yield 'bad mobile' => [['civ' => 'M.', 'nom' => 'MARTIN', 'pre' => 'Paul', 'mob' => '0112345678', 'fixe' => '0142000000'], 'Mobile invalide'];
        yield 'bad landline' => [self::OK + ['fixe' => '0612'], 'Fixe invalide'];
        yield 'bad e-mail' => [self::OK + ['mail' => 'anne@'], 'E-mail invalide'];
        yield 'no street' => [self::OK + ['cp' => '75008'], 'Adresse : n° et voie manquants'];
        yield 'bad postal code' => [self::OK + ['rue' => '12 Rue de Rome', 'cp' => '750'], 'Adresse : code postal invalide'];
        yield 'no commune' => [self::OK + ['rue' => '12 Rue de Rome', 'cp' => '75008'], 'Adresse : choisissez la commune'];
    }

    public function testAStructureIsTitledByItsLegalName(): void
    {
        $client = DirectoryClientInput::parse(self::OK + ['fisc' => 'pro', 'rs' => 'Élevage des Calanques', 'siren' => '523456788']);

        self::assertInstanceOf(DirectoryClientInput::class, $client);
        self::assertSame('Élevage des Calanques', $client->title());
        self::assertSame('french_business', $client->taxProfile?->toClientDto()->status);
    }

    public function testTheTaxProfileIsCheckedFirst(): void
    {
        self::assertSame('SIREN invalide : clé de contrôle incorrecte.', DirectoryClientInput::parse(['fisc' => 'pro', 'rs' => 'Calanques', 'siren' => '523456789']));
    }
}
