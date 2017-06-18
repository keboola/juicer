<?php

namespace Keboola\Juicer\Tests\Parser;

use Keboola\Juicer\Parser\JsonMap;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JsonMapTest extends TestCase
{
    public function testProcess()
    {
        $config = new Config('ex', []);

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
                    ],
                    'parent' => [
                        'type' => 'user',
                        'mapping' => ['destination' => 'parent_id']
                    ],
                ]
            ]
        ]);
        $parser = JsonMap::create($config, new NullLogger());

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
                '"item_id","tags","parent_id"' . PHP_EOL,
                '"1","","iAreId"' . PHP_EOL,
                '"2","593bf3944ed10e12aeafe50d03bc6cd5","iAreId"' . PHP_EOL
            ],
            file($parser->getResults()['first'])
        );
        self::assertEquals(
            [
                '"user","tag","first_pk"' . PHP_EOL,
                '"asd","tag1","593bf3944ed10e12aeafe50d03bc6cd5"' . PHP_EOL,
                '"asd","tag2","593bf3944ed10e12aeafe50d03bc6cd5"' . PHP_EOL
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
        $config = new Config('ex', []);
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
        JsonMap::create($config, new NullLogger());
    }

    public function testNoMappingFallback()
    {
        $config = new Config('ex', []);
        $config->setAttributes([
            'mappings' => [
                'notfirst' => [
                    'id' => [
                        'mapping' => [
                            'destination' => 'id'
                        ]
                    ]
                ]
            ]
        ]);

        $fallback = Json::create($config, new NullLogger(), new Temp());
        $parser = JsonMap::create($config, new NullLogger(), $fallback);

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
        $parser->process($data, 'notfirst');

        self::assertContainsOnlyInstancesOf('Keboola\CsvTable\Table', $parser->getResults());
        self::assertEquals(['notfirst', 'first', 'first_arr', 'first_tags'], array_keys($parser->getResults()));
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Empty mapping for 'first' in config.
     */
    public function testEmptyMappingError()
    {
        $config = new Config('ex', []);
        $config->setAttributes(['mappings' => ['first' => []]]);
        JsonMap::create($config, new NullLogger());
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Bad Json to CSV Mapping configuration: Key 'mapping.destination' is not set for column 'id'.
     */
    public function testBadMapping()
    {
        $config = new Config('ex', []);
        $config->setAttributes([
            'mappings' => [
                'first' => [
                    'id' => [
                        'type' => 'column',
                    ]
                ]
            ]
        ]);
        $parser = JsonMap::create($config, new NullLogger());

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
        $config = new Config('ex', []);
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
        $parser = JsonMap::create($config, new NullLogger());

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
        $configFirst = JobConfig::create([
            'endpoint' => '1st',
            'dataType' => 'first'
        ]);

        $configTags = JobConfig::create([
            'endpoint' => '2nd',
            'dataType' => 'tags'
        ]);

        $config = new Config('ex', []);
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

        $parser = JsonMap::create($config, new NullLogger());

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

    public function testMappingSimpleArrayToTable()
    {
        $config = new Config('ex', []);

        $config->setAttributes([
            'mappings' => [
                'reports' => [
                    'rows' => [
                        'type' => 'table',
                        'destination' => 'report-rows',
                        'tableMapping' => [
                            '0' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'date'
                                ]
                            ],
                            '1' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'unit_id'
                                ]
                            ],
                            '2' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'unit_name'
                                ]
                            ],
                            '3' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'clicks'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $parser = JsonMap::create($config, new NullLogger());
        $data = json_decode('[{
            "rows": [
                ["2017-05-27","1234","article-bot-lef-x","83008"],
                ["2017-05-27","5678","article-bot-mob-x","105723"]
            ]
        }]');

        $parser->process($data, 'reports');

        $expected = [
            '"date","unit_id","unit_name","clicks","reports_pk"' . PHP_EOL,
            '"2017-05-27","1234","article-bot-lef-x","83008","9568b51020c31f6e4e11f43ea8093967"' . PHP_EOL,
            '"2017-05-27","5678","article-bot-mob-x","105723","9568b51020c31f6e4e11f43ea8093967"' . PHP_EOL
        ];

        self::assertEquals($expected, file($parser->getResults()['report-rows']));
    }
}
