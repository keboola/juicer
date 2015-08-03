<?php

use	Keboola\Juicer\Parser\Parser;
use	Keboola\Csv\CsvFile;
use	Keboola\Temp\Temp;

class ParserTest extends ExtractorTestCase
{
	public function testGetTemp()
	{
		$parser = new Parser();
		$this->assertInstanceOf('\Keboola\Temp\Temp', self::callMethod($parser, 'getTemp', []));
	}
}
