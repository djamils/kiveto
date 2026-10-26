<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Link;

use App\System\IdentityAccess\Infrastructure\Link\PublicAccountLinkBuilder;
use PHPUnit\Framework\TestCase;

final class PublicAccountLinkBuilderTest extends TestCase
{
    public function testLinksPointToTheClinicHost(): void
    {
        $links = new PublicAccountLinkBuilder('https://clinic.kiveto.fr/');

        self::assertSame('https://clinic.kiveto.fr/invitation/abc-_1', $links->invitationUrl('abc-_1'));
        self::assertSame('https://clinic.kiveto.fr/reinitialiser/abc-_1', $links->passwordResetUrl('abc-_1'));
    }
}
