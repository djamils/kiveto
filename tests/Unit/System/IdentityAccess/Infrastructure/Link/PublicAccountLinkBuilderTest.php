<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Link;

use App\System\IdentityAccess\Infrastructure\Link\PublicAccountLinkBuilder;
use PHPUnit\Framework\TestCase;

final class PublicAccountLinkBuilderTest extends TestCase
{
    public function testLinksPointToTheClinicHost(): void
    {
        $links = new PublicAccountLinkBuilder('https://clinic.kiveto.app/');

        self::assertSame('https://clinic.kiveto.app/invitation/abc-_1', $links->invitationUrl('abc-_1'));
        self::assertSame('https://clinic.kiveto.app/reinitialiser/abc-_1', $links->passwordResetUrl('abc-_1'));
    }
}
