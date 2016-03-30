<?php

use Keboola\Juicer\Parser\JsonMap,
    Keboola\Juicer\Common\Logger,
    Keboola\Juicer\Config\Config,
    Keboola\Juicer\Config\JobConfig;
// use Keboola\Csv\CsvFile;
// use Keboola\Temp\Temp;

class JsonMapTest extends ExtractorTestCase
{
    public function testProcess()
    {
        $config = new Config('ex', 'test', []);
        $config->setJobs([
            JobConfig::create([
                'endpoint' => '1st',
                'dataType' => 'first',
                'dataMapping' => [
                    'id' => [
                        'type' => 'column',
                        'mapping' => ['destination' => 'item_id']
                    ]
                ]
            ]),
            JobConfig::create([
                'endpoint' => '2nd',
                'dataMapping' => [
                    'id' => [
                        'type' => 'column',
                        'mapping' => ['destination' => 'item_id']
                    ]
                ]
            ])
        ]);
        $parser = JsonMap::create($config);

        $data = json_decode('[
            {
                "id": 1,
                "arr": [1,2,3]
            },
            {
                "id": 2,
                "arr": ["a","b","c"]
            }
        ]');

        $parser->process($data, 'first', ['parent' => 'iAreId']);

        var_dump($parser->getResults());
    }
}
