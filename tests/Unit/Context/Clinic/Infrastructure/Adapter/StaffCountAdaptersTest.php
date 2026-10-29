<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Adapter;

use App\Context\Clinic\Application\Port\ClinicMessagingProfileData;
use App\Context\Clinic\Infrastructure\Adapter\IdentityAccess\QueryBusUserAccountStatus;
use App\Context\Clinic\Infrastructure\Adapter\Notification\NotificationMessagingProfileSync;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Presentation\Twig\PhoneFormatRuntime;
use App\System\IdentityAccess\Application\Query\GetUserStatuses\GetUserStatuses;
use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCount;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class StaffCountAdaptersTest extends TestCase
{
    public function testOnlyActiveAccountsAreKept(): void
    {
        $bus = new AnsweringQueryBus()->answer(GetUserStatuses::class, ['a' => 'ACTIVE', 'b' => 'PENDING', 'c' => 'DISABLED']);

        self::assertSame(['a'], new QueryBusUserAccountStatus($bus)->activeUserIds(['a', 'b', 'c', 'unknown']));
        self::assertInstanceOf(GetUserStatuses::class, $bus->asked[0]);
        self::assertSame(['a', 'b', 'c', 'unknown'], $bus->asked[0]->userIds);
    }

    public function testTheCountIsHandedToNotification(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(new RecordVeterinarianCount('c', '2026-10-26', 3));

        new NotificationMessagingProfileSync($bus, new PhoneFormatRuntime())->pushVeterinarianCount('c', '2026-10-26', 3);
    }

    public function testTheProfileIsHandedToNotificationWithItsPhonePrinted(): void
    {
        $dispatched = [];
        $bus        = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $command) use (&$dispatched): null {
            $dispatched[] = $command;

            return null;
        });

        $sync = new NotificationMessagingProfileSync($bus, new PhoneFormatRuntime());
        $sync->pushProfile(self::profile('+33142641800'));
        $sync->pushProfile(self::profile(null));

        self::assertCount(2, $dispatched);
        self::assertInstanceOf(UpsertClinicMessagingProfile::class, $dispatched[0]);
        self::assertSame([
            'clinicId'           => 'c',
            'name'               => 'Clinique Montmartre',
            'emailLocalPart'     => 'clinique-montmartre',
            'replyTo'            => 'contact@montmartre.fr',
            'smsSender'          => 'Montmartre',
            'overageCapSegments' => 500,
            'addressLine'        => '18 rue Lepic, 75018 Paris',
            'city'               => 'Paris',
            'phoneDisplay'       => '01 42 64 18 00',
            'brandColor'         => '#4f86c6',
            'logoStorageKey'     => 'logo.png',
        ], (array) $dispatched[0]);
        self::assertInstanceOf(UpsertClinicMessagingProfile::class, $dispatched[1]);
        self::assertSame('', $dispatched[1]->phoneDisplay);
    }

    private static function profile(?string $phone): ClinicMessagingProfileData
    {
        return new ClinicMessagingProfileData('c', 'Clinique Montmartre', 'clinique-montmartre', 'contact@montmartre.fr', 'Montmartre', 500, '18 rue Lepic, 75018 Paris', 'Paris', $phone, '#4f86c6', 'logo.png');
    }
}
