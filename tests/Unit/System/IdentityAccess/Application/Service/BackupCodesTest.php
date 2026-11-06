<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Service;

use App\Tests\Unit\System\IdentityAccess\Support\SecondFactorFakes;
use PHPUnit\Framework\TestCase;

final class BackupCodesTest extends TestCase
{
    public function testTenFreshCodesComeWithTheirDigests(): void
    {
        $codes = new SecondFactorFakes()->backupCodes()->fresh();

        self::assertSame(['KVT01-ABCDE', 'KVT02-ABCDE', 'KVT03-ABCDE', 'KVT04-ABCDE', 'KVT05-ABCDE', 'KVT06-ABCDE', 'KVT07-ABCDE', 'KVT08-ABCDE', 'KVT09-ABCDE', 'KVT10-ABCDE'], $codes['plain']);
        self::assertSame('h:backup:KVT01ABCDE', $codes['hashes'][0]);
        self::assertSame('h:backup:KVT10ABCDE', $codes['hashes'][9]);
        self::assertCount(10, $codes['hashes']);
    }

    /**
     * A code drawn again, even written otherwise, is replaced by another
     * draw: the ten codes are distinct.
     */
    public function testARepeatedCodeIsDrawnAgain(): void
    {
        $fakes                  = new SecondFactorFakes();
        $fakes->nextBackupCodes = ['AAAAA-AAAAA', 'aaaaa aaaaa', 'AAAAAAAAAA', 'BBBBB-BBBBB'];

        $codes = $fakes->backupCodes()->fresh();

        self::assertSame(['AAAAAAAAAA', 'BBBBB-BBBBB', 'KVT01-ABCDE', 'KVT02-ABCDE', 'KVT03-ABCDE', 'KVT04-ABCDE', 'KVT05-ABCDE', 'KVT06-ABCDE', 'KVT07-ABCDE', 'KVT08-ABCDE'], $codes['plain']);
        self::assertCount(10, array_unique($codes['hashes']));
        self::assertSame(['h:backup:AAAAAAAAAA', 'h:backup:BBBBBBBBBB'], \array_slice($codes['hashes'], 0, 2));
    }

    public function testASourceThatKeepsRepeatingItselfIsGivenUp(): void
    {
        $fakes                  = new SecondFactorFakes();
        $fakes->nextBackupCodes = array_fill(0, 100, 'KVT01-ABCDE');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The backup code source keeps repeating itself.');

        $fakes->backupCodes()->fresh();
    }

    public function testCaseSpacesAndDashesOfATypedCodeDoNotCount(): void
    {
        $codes = new SecondFactorFakes()->backupCodes();

        self::assertSame('h:backup:K7MQ2XW9PD', $codes->hash('K7MQ2-XW9PD'));
        self::assertSame('h:backup:K7MQ2XW9PD', $codes->hash(" k7mq2 - xw9pd\t"));
        self::assertSame('h:backup:K7MQ2XW9PD', $codes->hash('k7mq2xw9pd'));
    }
}
