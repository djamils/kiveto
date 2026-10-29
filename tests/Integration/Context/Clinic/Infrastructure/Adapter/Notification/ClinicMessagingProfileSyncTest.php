<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Adapter\Notification;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicMessagingSettings\UpdateClinicMessagingSettings;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 4b (Clinic side): a clinic created without domain events gets its
 * profile from `app:clinic:sync-messaging-profiles`; later changes follow
 * through the domain-event bridge.
 */
final class ClinicMessagingProfileSyncTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '018f1b1e-eeee-7890-abcd-0123456789ab';

    public function testTheConsoleCommandThenTheBridgeKeepNotificationsCopyCurrent(): void
    {
        ClinicEntityFactory::createOne(['id' => Uuid::fromString(self::CLINIC_ID), 'name' => 'Clinique des Lilas', 'slug' => 'clinique-des-lilas']);

        self::assertNull($this->profiles()->find(self::CLINIC_ID));

        $application = new Application(self::$kernel ?? self::bootKernel());
        $tester      = new CommandTester($application->find('app:clinic:sync-messaging-profiles'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('synchronised', $tester->getDisplay());

        $profile = $this->profiles()->find(self::CLINIC_ID);
        self::assertNotNull($profile);
        self::assertSame('Clinique des Lilas', $profile->name);
        self::assertSame('clinique-des-lilas', $profile->emailLocalPart);
        self::assertSame('Clinique de', $profile->smsSender);

        $bus = static::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $bus->dispatch(new UpdateClinicMessagingSettings(self::CLINIC_ID, 'Lilas Vet', 200));
        $bus->dispatch(new UpdateClinicLetterhead(
            clinicId: self::CLINIC_ID,
            streetLine1: '3 rue des Lilas',
            postalCode: '75019',
            city: 'Paris',
            countryCode: 'FR',
            phone: '+33142000000',
            email: 'accueil@lilas.fr',
        ));

        $profile = $this->profiles()->find(self::CLINIC_ID);
        self::assertNotNull($profile);
        self::assertSame('Lilas Vet', $profile->smsSender);
        self::assertSame(200, $profile->overageCapSegments);
        self::assertSame('3 rue des Lilas, 75019 Paris', $profile->addressLine);
        self::assertSame('01 42 00 00 00', $profile->phoneDisplay);
        self::assertSame('accueil@lilas.fr', $profile->replyTo);
    }

    private function profiles(): ClinicMessagingProfileRepositoryInterface
    {
        $profiles = static::getContainer()->get('test.' . ClinicMessagingProfileRepositoryInterface::class);
        \assert($profiles instanceof ClinicMessagingProfileRepositoryInterface);

        return $profiles;
    }
}
