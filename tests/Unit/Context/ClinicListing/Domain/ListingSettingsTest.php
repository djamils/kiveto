<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Domain;

use App\Context\ClinicListing\Domain\Exception\InvalidListingSettingsException;
use App\Context\ClinicListing\Domain\ListingSettings;
use PHPUnit\Framework\TestCase;

final class ListingSettingsTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000a1c01';

    public function testTheDefaultsKeepTheClinicOutOfTheDirectory(): void
    {
        $settings = ListingSettings::defaults(self::CLINIC);

        self::assertSame([self::CLINIC, false, true, []], [$settings->clinicId(), $settings->isListed(), $settings->acceptsNewClients(), $settings->tags()]);
    }

    public function testTagsAreCleanedAndDeduplicated(): void
    {
        $settings = ListingSettings::reconstitute(self::CLINIC, false, true, ['Chats']);
        $settings->update(true, false, ['  NAC ', 'nac', '', 'Urgences   24 h', 'Chats']);

        self::assertTrue($settings->isListed());
        self::assertFalse($settings->acceptsNewClients());
        self::assertSame(['NAC', 'Urgences 24 h', 'Chats'], $settings->tags());

        $settings->update(true, true, array_map(static fn (int $i): string => 'Tag ' . $i, range(1, ListingSettings::MAX_TAGS)));
        self::assertCount(ListingSettings::MAX_TAGS, $settings->tags());
    }

    public function testATagTooShortOrTooLongIsRefusedAndNothingChanges(): void
    {
        $settings = ListingSettings::reconstitute(self::CLINIC, true, true, ['Chats']);

        foreach (['A', str_repeat('é', ListingSettings::TAG_MAX_LENGTH + 1)] as $tag) {
            try {
                $settings->update(false, false, ['NAC', $tag]);
                self::fail('The tag should be refused.');
            } catch (InvalidListingSettingsException $e) {
                self::assertSame('tags', $e->field);
                self::assertSame(\sprintf('« %s » : un tag compte de 2 à 30 caractères.', $tag), $e->getMessage());
            }
        }

        self::assertSame([true, true, ['Chats']], [$settings->isListed(), $settings->acceptsNewClients(), $settings->tags()]);
    }

    public function testTooManyTagsAreRefused(): void
    {
        $settings = ListingSettings::defaults(self::CLINIC);

        $this->expectException(InvalidListingSettingsException::class);
        $this->expectExceptionMessage('8 tags au plus.');

        $settings->update(true, true, array_map(static fn (int $i): string => 'Tag ' . $i, range(1, ListingSettings::MAX_TAGS + 1)));
    }
}
