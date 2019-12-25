<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../src/classes/Fees.php";

final class FeesTest extends TestCase
{
    public function testCanParseCsv(): void
    {
        $this->assertInstanceOf(
            src\classes\Fees::class,
            src\classes\Fees::fromString('input.csv')
        );
    }

    public function testCannotParseInvalidCsv(): void
    {
        $this->expectException(Exception::class);

        src\classes\Fees::fromString('invalid');
    }
    
    public function testCannotParseRow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        src\classes\Fees::checkLine([
            '2018-05-05',
            '2',
            'legal',
            'cash_in',
            '1000',
            'EURO',
        ]);
    }

    public function testCanParseRow(): void
    {
        $this->assertEquals(
            [
                '2018-05-05',
                '2',
                'legal',
                'cash_in',
                '1000',
                'EUR',
            ],
            src\classes\Fees::checkLine([
                '2018-05-05',
                '2',
                'legal',
                'cash_in',
                '1000',
                'EUR',
            ])
        );
    }
}
