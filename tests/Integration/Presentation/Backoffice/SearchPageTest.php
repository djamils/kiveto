<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Tests\Support\Console\SignsInToTheConsole;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * ⌘K — the console's search: clinics, staff, owners, features, pages.
 */
final class SearchPageTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private const string PRADO = '01960000-0000-7000-8000-00000000c1a1';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->createConsoleAdmin();
        $this->signInToTheConsole($this->browser);

        ClinicEntityFactory::new()->withId(self::PRADO)->withName('Clinique du Prado')->withSlug('prado-search')->create();
        $claire     = ClinicUserFactory::new()->withEmail('c.martin@example.com')->create();
        $membership = ClinicMembershipEntityFactory::new()->withClinicId(self::PRADO)->withUserId($claire->getId()->toRfc4122())->withRole(ClinicMemberRole::VETERINARY)->asEmployee()->create();
        StaffProfileEntityFactory::new()->forMembership($membership->getId())->asDoctor()->with(['firstName' => 'Claire', 'lastName' => 'Martin'])->create();
        PortalUserFactory::new()->withEmail('marie.prado@example.com')->create();
    }

    public function testThePaletteAsksTheSearch(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/clinics');
        self::assertSelectorExists('.co-search[data-controller=co-palette][data-co-palette-url-value="/search"]');

        $this->browser->request('GET', self::CONSOLE . '/search?q=prado', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($json) && \is_array($json['groups']));

        $labels = [];
        foreach ($json['groups'] as $group) {
            \assert(\is_array($group) && \is_array($group['items']));
            foreach ($group['items'] as $item) {
                \assert(\is_array($item));
                $labels[] = (\is_string($group['label'] ?? null) ? $group['label'] : '') . ' > ' . (\is_string($item['label'] ?? null) ? $item['label'] : '');
            }
        }

        self::assertContains('Cliniques > Clinique du Prado', $labels);
        self::assertContains('Propriétaires (Portal) > marie.prado@example.com', $labels);
    }

    public function testStaffFeaturesAndPages(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/search?q=martin', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertStringContainsString('Dr Claire Martin', (string) $this->browser->getResponse()->getContent());

        $this->browser->request('GET', self::CONSOLE . '/search?q=sms', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertStringContainsString('Rappels SMS', (string) $this->browser->getResponse()->getContent());

        $this->browser->request('GET', self::CONSOLE . '/search?q=journal', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertStringContainsString('/audit', (string) $this->browser->getResponse()->getContent());
    }

    public function testThePageWithoutJavaScript(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/search?q=p');
        self::assertSelectorTextContains('.co-empty', 'Tape au moins deux caractères');

        $this->browser->request('GET', self::CONSOLE . '/search?q=introuvable-xyz');
        self::assertSelectorTextContains('.co-empty', 'Aucun résultat');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/search?q=prado');
        self::assertSelectorTextContains('.co-search-group', 'Cliniques');
        self::assertSame('/clinics/' . self::PRADO, $crawler->filter('.co-search-results a')->first()->attr('href'));
    }
}
