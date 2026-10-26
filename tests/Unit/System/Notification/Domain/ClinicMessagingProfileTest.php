<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Domain;

use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicMessagingProfileTest extends TestCase
{
    public function testAProfileIsNormalised(): void
    {
        $profile = ClinicMessagingProfile::of(Messages::CLINIC, ' Côte d’Opale ', ' Cote-Opale ', ' ', ' Opale Vet ', 0, ' 1 quai ', ' Calais ', ' 03 21 00 00 00 ', ' ', ' ');

        self::assertSame(['Côte d’Opale', 'cote-opale', null, 'Opale Vet', 0], [$profile->name, $profile->emailLocalPart, $profile->replyTo, $profile->smsSender, $profile->overageCapSegments]);
        self::assertSame(['1 quai', 'Calais', '03 21 00 00 00', null, null], [$profile->addressLine, $profile->city, $profile->phoneDisplay, $profile->brandColor, $profile->logoStorageKey]);
        self::assertSame('C', $profile->initial());
        self::assertSame('clinic/logo.png', InMemoryProfiles::montmartre(logo: 'clinic/logo.png')->logoStorageKey);
        self::assertSame('#4338CA', InMemoryProfiles::montmartre()->brandColor);
    }

    /**
     * @return iterable<string, array{string, string, ?string, string, int, ?string}>
     */
    public static function provideInvalidProfilesCases(): iterable
    {
        yield 'no name' => [' ', 'montmartre', null, 'Montmartre', 500, null];
        yield 'local part' => ['Montmartre', 'mont martre', null, 'Montmartre', 500, null];
        yield 'reply-to' => ['Montmartre', 'montmartre', 'nope', 'Montmartre', 500, null];
        yield 'sms sender too long' => ['Montmartre', 'montmartre', null, 'Clinique des Lilas', 500, null];
        yield 'sms sender accents' => ['Montmartre', 'montmartre', null, 'Côte', 500, null];
        yield 'negative cap' => ['Montmartre', 'montmartre', null, 'Montmartre', -1, null];
        yield 'colour' => ['Montmartre', 'montmartre', null, 'Montmartre', 500, 'blue'];
    }

    #[DataProvider('provideInvalidProfilesCases')]
    public function testInvalidProfiles(string $name, string $local, ?string $replyTo, string $sms, int $cap, ?string $color): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ClinicMessagingProfile::of(Messages::CLINIC, $name, $local, $replyTo, $sms, $cap, '', '', '', $color, null);
    }
}
