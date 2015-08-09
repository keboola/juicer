<?php

use	Keboola\Juicer\Parser\Json;
use	Keboola\Json\Parser;
use	Keboola\Csv\CsvFile;
use	Keboola\Temp\Temp;

class JsonTest extends ExtractorTestCase
{
	public function testProcess()
	{
		$parser = new Json(new Parser($this->getLogger('test', true)));

		$data = json_decode('[
			{
				"pk": 1,
				"arr": [1,2,3]
			},
			{
				"pk": 2,
				"arr": ["a","b","c"]
			}
		]');

		$parser->process($data, 'test', ['parent' => 'iAreId']);

		$this->assertEquals(
			'"pk","arr","parent"
"1","test_2901753343d19a32b8cd49e31aab748c","iAreId"
"2","test_5e36066fa62399eedd858f5e374c0c21","iAreId"
',
			file_get_contents($parser->getResults()['test'])
		);

		$this->assertEquals(
			'"data","JSON_parentId"
"1","test_2901753343d19a32b8cd49e31aab748c"
"2","test_2901753343d19a32b8cd49e31aab748c"
"3","test_2901753343d19a32b8cd49e31aab748c"
"a","test_5e36066fa62399eedd858f5e374c0c21"
"b","test_5e36066fa62399eedd858f5e374c0c21"
"c","test_5e36066fa62399eedd858f5e374c0c21"
',
			file_get_contents($parser->getResults()['test_arr'])
		);
	}
}
