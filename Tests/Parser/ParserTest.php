<?php

namespace Keboola\Juicer\Tests\Parser;

use Keboola\Juicer\Parser\Parser;
use Keboola\Juicer\Tests\ExtractorTestCase;

class ParserTest extends ExtractorTestCase
{
    public function testGetTemp()
    {
        $parser = new Parser();
        self::assertInstanceOf('\Keboola\Temp\Temp', self::callMethod($parser, 'getTemp', []));
    }
}
