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
                    ],
                    'tags' => [
                        'type' => 'table',
                        'destination' => 'tags',
                        'tableMapping' => [
                            'user' => [
                                'mapping' => [
                                    'destination' => 'user',
                                    'primaryKey' => true
                                ]
                            ],
                            'tag' => [
                                'mapping' => [
                                    'destination' => 'tag',
                                    'primaryKey' => true
                                ]
                            ]
                        ]
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
                "arr": ["a","b","c"],
                "tags": [
                    {
                        "user": "asd",
                        "tag": "tag1"
                    },
                    {
                        "user": "asd",
                        "tag": "tag2"
                    }
                ]
            }
        ]');

        $parser->process($data, 'first', ['parent' => 'iAreId']);

        self::assertEquals(
            [
                '"item_id","tags"' . PHP_EOL,
                '"1",""' . PHP_EOL,
                '"2","675d5912d25c9220fbe677fbf35bfd09"' . PHP_EOL
            ],
            file($parser->getResults()['first'])
        );
        self::assertEquals(
            [
                '"user","tag","first_pk"' . PHP_EOL,
                '"asd","tag1","675d5912d25c9220fbe677fbf35bfd09"' . PHP_EOL,
                '"asd","tag2","675d5912d25c9220fbe677fbf35bfd09"' . PHP_EOL
            ],
            file($parser->getResults()['tags'])
        );

        self::assertEquals(['user', 'tag'], $parser->getResults()['tags']->getPrimaryKey(true));
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Missing 'dataMapping' for 'first' in config.
     */
    public function testNoMapping()
    {
        $config = new Config('ex', 'test', []);
        $config->setJobs([
            JobConfig::create([
                'endpoint' => '1st',
                'dataType' => 'first'
            ])
        ]);
        $parser = JsonMap::create($config);
    }
}
