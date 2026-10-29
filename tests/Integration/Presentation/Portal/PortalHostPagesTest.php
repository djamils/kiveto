<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Portal;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * D20: the invitation link's landing page and the app-link files, public on
 * the portal host.
 */
final class PortalHostPagesTest extends WebTestCase
{
    private const string HOST = 'http://portal.kiveto.local';

    /** AC 38. */
    public function testTheLandingPageSendsToTheStoresWithoutReferrer(): void
    {
        $browser = self::createClient();
        $browser->request('GET', self::HOST . '/invitation');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $html = (string) $browser->getResponse()->getContent();
        self::assertStringContainsString("Installez l'app puis connectez-vous avec votre numéro", html_entity_decode($html, \ENT_QUOTES));
        self::assertStringContainsString('https://apps.apple.com/app/kiveto', $html);
        self::assertStringContainsString('https://play.google.com/store/apps/details?id=app.kiveto.portal', $html);
    }

    /** AC 59. */
    public function testTheAppLinkFilesArePublicJson(): void
    {
        $browser = self::createClient();

        $browser->request('GET', self::HOST . '/.well-known/apple-app-site-association');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(
            ['applinks' => ['details' => [['appIDs' => ['ABCDE12345.app.kiveto.portal'], 'components' => [['/' => '/invitation*']]]]]],
            json_decode((string) $browser->getResponse()->getContent(), true),
        );

        $browser->request('GET', self::HOST . '/.well-known/assetlinks.json');
        self::assertResponseIsSuccessful();
        self::assertSame(
            [['relation' => ['delegate_permission/common.handle_all_urls'], 'target' => ['namespace' => 'android_app', 'package_name' => 'app.kiveto.portal', 'sha256_cert_fingerprints' => []]]],
            json_decode((string) $browser->getResponse()->getContent(), true),
        );
    }
}
