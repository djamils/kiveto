<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCount;
use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCountHandler;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfileHandler;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use App\Tests\Unit\System\Notification\Support\InMemoryVeterinarianCounts;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;

final class ClinicDataHandlersTest extends TestCase
{
    public function testClinicPushesItsMessagingProfile(): void
    {
        $profiles = new InMemoryProfiles();
        $handler  = new UpsertClinicMessagingProfileHandler($profiles);

        $handler(new UpsertClinicMessagingProfile(Messages::CLINIC, 'Clinique Montmartre', 'clinique-montmartre', 'accueil@clinique-montmartre.fr', 'Montmartre', 500, '18 rue Lepic, 75018 Paris', 'Paris 18e', '01 42 64 18 00', '#4338CA', 'clinic/logo.png'));
        $handler(new UpsertClinicMessagingProfile(Messages::CLINIC, 'Clinique des Lilas', 'clinique-des-lilas', null, 'Lilas Vet', 200, '', '', '', null, null));

        $profile = $profiles->find(Messages::CLINIC);
        self::assertNotNull($profile);
        self::assertSame(['Clinique des Lilas', 'clinique-des-lilas', 'Lilas Vet', 200], [$profile->name, $profile->emailLocalPart, $profile->smsSender, $profile->overageCapSegments]);
    }

    public function testClinicPushesTheVeterinarianCountOfADay(): void
    {
        $counts  = new InMemoryVeterinarianCounts();
        $handler = new RecordVeterinarianCountHandler($counts);

        $handler(new RecordVeterinarianCount(Messages::CLINIC, '2026-10-12', 3));
        $handler(new RecordVeterinarianCount(Messages::CLINIC, '2026-10-12', 2));

        self::assertSame(['2026-10-12' => 2], $counts->counts[Messages::CLINIC]);
    }

    public function testAnInvalidDayIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecordVeterinarianCountHandler(new InMemoryVeterinarianCounts())(new RecordVeterinarianCount(Messages::CLINIC, '2026-02-30', 3));
    }

    public function testANegativeCountIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecordVeterinarianCountHandler(new InMemoryVeterinarianCounts())(new RecordVeterinarianCount(Messages::CLINIC, '2026-02-03', -1));
    }
}
