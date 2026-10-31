<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Service;

use App\Context\Consultation\Application\Service\VisitTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VisitTitleTest extends TestCase
{
    /**
     * @param list<string> $motifs
     */
    #[DataProvider('provideTitlesAVisitCases')]
    public function testTitlesAVisit(array $motifs, ?string $chiefComplaint, string $expected): void
    {
        self::assertSame($expected, VisitTitle::of($motifs, $chiefComplaint));
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: ?string, 2: string}>
     */
    public static function provideTitlesAVisitCases(): iterable
    {
        yield 'single motif kept as is' => [['Vaccination'], 'Rappel annuel.', 'Vaccination'];
        yield 'following motifs lowercased on their first letter' => [['Vaccination', 'Bilan annuel'], null, 'Vaccination et bilan annuel'];
        yield 'three motifs' => [['Boiterie', 'Otite', 'Contrôle'], null, 'Boiterie et otite et contrôle'];
        yield 'multibyte first letter' => [['Vaccination', 'Échographie'], null, 'Vaccination et échographie'];
        yield 'only the first letter is lowercased' => [['Vaccination', 'FIV FeLV'], null, 'Vaccination et fIV FeLV'];
        yield 'chief complaint without motif' => [[], 'Vomissements depuis hier', 'Vomissements depuis hier'];
        yield 'trailing dots and spaces stripped' => [[], 'Toux persistante. . ', 'Toux persistante'];
        yield 'neither motif nor complaint' => [[], null, 'Consultation'];
    }
}
