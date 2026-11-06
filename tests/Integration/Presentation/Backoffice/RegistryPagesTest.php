<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Backoffice;

use App\Tests\Support\Console\SignsInToTheConsole;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * G1–G4 — the ANMV registry of the console: the latest import (done,
 * running, failed), the history, a diff and « Lancer l'import ».
 */
final class RegistryPagesTest extends WebTestCase
{
    use Factories;
    use SignsInToTheConsole;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->createConsoleAdmin();
        $this->signInToTheConsole($this->browser);
        $this->connection()->executeStatement('DELETE FROM pharmaceutical_registry__snapshots');
    }

    public function testNoImportYet(): void
    {
        $this->browser->request('GET', self::CONSOLE . '/registry');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.co-tab.is-active', 'Registre ANMV');
        self::assertSelectorTextContains('[aria-labelledby=registry-last]', 'Aucun import pour l\'instant');
        self::assertSelectorTextContains('.co-banner.is-info', 'Prochain fichier attendu');
        self::assertSelectorTextContains('.co-dl', 'Amorçage initial');
    }

    public function testTheLatestImportAndItsDiff(): void
    {
        $this->snapshot('-14 days', 'APPLIED', ['CREATE' => 1]);
        $this->snapshot('-7 days', 'FAILED', [], 'Fichier tronqué');
        $latest = $this->snapshot('-1 hour', 'APPLIED', ['CREATE' => 3, 'UPDATE' => 9, 'WITHDRAW' => 2]);

        $crawler = $this->browser->request('GET', self::CONSOLE . '/registry');
        self::assertSelectorTextContains('[aria-labelledby=registry-last] .co-badge', 'Réussi');
        self::assertSelectorTextContains('.co-registry-stats', '+3');
        self::assertSelectorTextContains('.co-registry-stats', '9');
        self::assertSame(['Réussi', 'Échec', 'Réussi'], $crawler->filter('.co-registry-history tbody .co-badge')->each(static fn (Crawler $b): string => $b->text()));
        self::assertStringContainsString('Annulé, aucune écriture', $crawler->filter('.co-registry-history tbody tr')->eq(1)->text());
        self::assertStringContainsString('+3 · 9 modifiées · 2 retirées', $crawler->filter('.co-registry-history tbody tr')->eq(0)->text());

        $crawler = $this->browser->request('GET', self::CONSOLE . '/registry/imports/' . $latest);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Import du');
        self::assertSelectorTextContains('.co-pill.is-active', 'Tout 14');
        self::assertCount(10, $crawler->filter('.co-registry-diff tbody tr'));
        self::assertSelectorTextContains('.co-registry-diff tbody tr:first-child', 'Retirée');
        self::assertSelectorTextContains('.co-pager', '1–10 sur 14');

        $crawler = $this->browser->request('GET', self::CONSOLE . '/registry/imports/' . $latest . '?kind=updated&page=2');
        self::assertSelectorTextContains('.co-pill.is-active', 'Modifiées 9');
        self::assertCount(9, $crawler->filter('.co-registry-diff tbody tr'), 'the page is brought back to the last one');
        self::assertSelectorTextContains('.co-registry-diff', 'Modifié : titulaire de l’AMM');

        $this->browser->request('GET', self::CONSOLE . '/registry/imports/' . $latest . '/export');
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = (string) $this->browser->getInternalResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF\"N° AMM\";Médicament;Laboratoire;Changement;", $csv);
        self::assertStringContainsString(';Modifiée;"titulaire de l’AMM"', $csv);
        self::assertSame(15, substr_count($csv, "\n"));

        foreach (['', '/export'] as $page) {
            $this->browser->request('GET', self::CONSOLE . '/registry/imports/01960000-0000-7000-8000-00000000dead' . $page);
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testARunningAndAFailedImport(): void
    {
        $this->snapshot('-2 days', 'APPLIED', ['UPDATE' => 1]);
        $this->snapshot('-5 minutes', 'RUNNING', []);
        $this->browser->request('GET', self::CONSOLE . '/registry');
        self::assertSelectorTextContains('.co-registry-running', 'Import en cours');
        self::assertSelectorExists('.co-head-actions button[disabled]');

        $this->post();
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Un import est déjà en cours.');

        $this->connection()->executeStatement("UPDATE pharmaceutical_registry__snapshots SET status = 'FAILED', error_message = 'Dictionnaire illisible' WHERE status = 'RUNNING'");
        $this->browser->request('GET', self::CONSOLE . '/registry');
        self::assertSelectorTextContains('.co-registry-failed', 'Le dernier import a échoué');
        self::assertSelectorTextContains('.co-registry-failed', 'Dictionnaire illisible');
    }

    public function testNothingToImport(): void
    {
        $this->post();

        self::assertResponseRedirects('/registry');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.co-flashes', 'Aucun fichier à importer');

        $this->browser->request('POST', self::CONSOLE . '/registry/import', ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);
    }

    private function post(): void
    {
        $crawler = $this->browser->request('GET', self::CONSOLE . '/registry');
        $this->browser->submit($crawler->filter('.co-head-actions')->form());
    }

    /**
     * @param array<string, int> $changes
     */
    private function snapshot(string $when, string $status, array $changes, ?string $error = null): string
    {
        $id = Uuid::v7();
        $at = new \DateTimeImmutable($when, new \DateTimeZone('UTC'));
        $this->connection()->insert('pharmaceutical_registry__snapshots', [
            'id'            => $id->toBinary(),
            'source'        => 'ANMV',
            'status'        => $status,
            'downloaded_at' => $at->format('Y-m-d H:i:s'),
            'applied_at'    => 'APPLIED' === $status ? $at->modify('+40 seconds')->format('Y-m-d H:i:s') : null,
            'error_message' => $error,
        ]);

        $n = 0;
        foreach ($changes as $kind => $count) {
            for ($i = 0; $i < $count; ++$i, ++$n) {
                $this->connection()->insert('pharmaceutical_registry__snapshot_entries', [
                    'id'                   => Uuid::v7()->toBinary(),
                    'snapshot_id'          => $id->toBinary(),
                    'authority_identifier' => \sprintf('FR/V/%07d', $n),
                    'content_hash'         => hash('sha256', $id->toRfc4122() . $n),
                    'raw_dto'              => json_encode(['commercialName' => 'Produit ' . $n, 'holderLaboratoryLabel' => 'Labo'], \JSON_THROW_ON_ERROR),
                    'diff_kind'            => $kind,
                    'changes'              => 'UPDATE' === $kind ? json_encode(['holderLaboratoryLabel'], \JSON_THROW_ON_ERROR) : null,
                ]);
            }
        }

        return $id->toRfc4122();
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
