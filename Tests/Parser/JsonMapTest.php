<?php

use Keboola\Juicer\Parser\JsonMap,
    Keboola\Juicer\Common\Logger,
    Keboola\Juicer\Config\Config,
    Keboola\Juicer\Config\JobConfig;

class JsonMapTest extends ExtractorTestCase
{
    public function testProcess()
    {
        $config = new Config('ex', 'test', []);

        $config->setAttributes([
            'mappings' => [
                'first' => [
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
            ]
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

        $parser->process($data, 'first');

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
     * @expectedExceptionMessage Missing mapping for 'first' in config.
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
        $config->setAttributes([
            'mappings' => [
                'notfirst' => [
                    'id' => [
                        'type' => 'column',
                    ]
                ]
            ]
        ]);
        $parser = JsonMap::create($config);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Empty mapping for 'first' in config.
     */
    public function testEmptyMappingError()
    {
        $config = new Config('ex', 'test', []);
        $config->setAttributes([
            'mappings' => [
                'first' => [
//                     'id' => [
//                         'type' => 'column',
//                     ]
                ]
            ]
        ]);
        $parser = JsonMap::create($config);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Bad Json to CSV Mapping configuration: Key 'mapping.destination' is not set for column 'id'.
     */
    public function testBadMapping()
    {
        $config = new Config('ex', 'test', []);
        $config->setAttributes([
            'mappings' => [
                'first' => [
                    'id' => [
                        'type' => 'column',
                    ]
                ]
            ]
        ]);
        $parser = JsonMap::create($config);

        $data = json_decode('[
            {
                "id": 1,
                "arr": [1,2,3]
            }
        ]');

        $parser->process($data, 'first', ['parent' => 'iAreId']);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Error saving 'first' data to CSV column: Error writing 'col' column: Cannot write object into a column
     */
    public function testBadData()
    {
        $config = new Config('ex', 'test', []);
        $config->setAttributes([
            'mappings' => [
                'first' => [
                    'obj' => [
                        'mapping' => [
                            'destination' => 'col'
                        ]
                    ]
                ]
            ]
        ]);
        $parser = JsonMap::create($config);

        $data = json_decode('[
            {
                "obj": {
                    "id": 1
                }
            }
        ]');

        $parser->process($data, 'first', ['parent' => 'iAreId']);
    }

    public function testMergeResults()
    {
        Logger::setLogger($this->getLogger('testMergeResults', true));

        $configFirst = JobConfig::create([
            'endpoint' => '1st',
            'dataType' => 'first'
        ]);

        $configTags = JobConfig::create([
            'endpoint' => '2nd',
            'dataType' => 'tags'
        ]);

        $config = new Config('ex', 'test', []);
        $config->setAttributes([
            'mappings' => [
                'first' => [
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
                        ],
                        'parentKey' => [
                            'disable' => true
                        ]
                    ]
                ],
                'tags' => [
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
        ]);

        $firstData = json_decode('[
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

        $secondData = json_decode('[
            {
                "user": "asd",
                "tag": "tag3"
            },
            {
                "user": "asd",
                "tag": "tag4"
            }
        ]');

        $parser = JsonMap::create($config);

        $parser->process($firstData, $configFirst->getDataType());
        $parser->process($secondData, $configTags->getDataType());

        self::assertEquals(
            [
                '"user","tag"' . PHP_EOL,
                '"asd","tag1"' . PHP_EOL,
                '"asd","tag2"' . PHP_EOL,
                '"asd","tag3"' . PHP_EOL,
                '"asd","tag4"' . PHP_EOL
            ],
            file($parser->getResults()['tags'])
        );
    }
}
