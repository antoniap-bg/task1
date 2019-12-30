<?php
declare(strict_types=1);

namespace Task;

require_once __DIR__ . "/../src/classes/Fees.php";

use PHPUnit\Framework\TestCase;
use Task\Fees;

final class FeesTest extends TestCase
{
    public function testCanParseCsv(): void
    {
        $this->assertInstanceOf(
            Fees::class,
            Fees::fromString('input.csv')
        );
    }

    public function testCannotParseInvalidCsv(): void
    {
        $this->expectException(\Exception::class);

        Fees::fromString('invalid');
    }
    
    public function testCanParseRow(): void
    {
        Fees::checkLine([
            '2018-05-05',
            '2',
            'legal',
            'cash_in',
            '1000',
            'EUR',
        ], 2);

        $this->addToAssertionCount(1);
    }

    public function testCannotParseRow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Fees::checkLine([
            '2018-05-05',
            '2',
            'legal',
            'cash_in',
            '1000',
            'EURO',
        ], 1);
    }
}
