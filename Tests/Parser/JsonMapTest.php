<?php

namespace Keboola\Juicer\Tests\Parser;

use Keboola\Juicer\Parser\JsonMap;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JsonMapTest extends TestCase
{
    public function testProcess()
    {
        $data = [
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
            ],
            'jobs' => [['endpoint' => 'first']]
        ];
        $config = new Config($data);
        $parser = new JsonMap($config, new NullLogger());

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
                '"item_id","tags","parent_id"' . "\n",
                '"1","","iAreId"' . "\n",
                '"2","593bf3944ed10e12aeafe50d03bc6cd5","iAreId"' . "\n"
            ],
            file($parser->getResults()['first'])
        );
        self::assertEquals(
            [
                '"user","tag","first_pk"' . "\n",
                '"asd","tag1","593bf3944ed10e12aeafe50d03bc6cd5"' . "\n",
                '"asd","tag2","593bf3944ed10e12aeafe50d03bc6cd5"' . "\n"
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
        $data = [
            'mappings' => [
                'notfirst' => [
                    'id' => [
                        'type' => 'column',
                    ]
                ]
            ],
            'jobs' => [['endpoint' => '1st', 'dataType' => 'first']]
        ];
        $config = new Config($data);
        new JsonMap($config, new NullLogger());
    }

    public function testNoMappingFallback()
    {
        $data = [
            'mappings' => [
                'notfirst' => [
                    'id' => [
                        'mapping' => [
                            'destination' => 'id'
                        ]
                    ]
                ]
            ],
            'jobs' => [['endpoint' => 'fooBar']]
        ];
        $config = new Config($data);
        $fallback = new Json(new NullLogger(), [], Json::LATEST_VERSION);
        $parser = new JsonMap($config, new NullLogger(), $fallback);

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
        $data = [
            'mappings' => ['first' => []],
            'jobs' => [['endpoint' => 'fooBar']]
        ];
        $config = new Config($data);
        new JsonMap($config, new NullLogger());
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Bad Json to CSV Mapping configuration: Key 'mapping.destination' is not set for column 'id'.
     */
    public function testBadMapping()
    {
        $data = [
            'mappings' => [
                'first' => [
                    'id' => [
                        'type' => 'column',
                    ]
                ]
            ],
            'jobs' => [['endpoint' => 'first']]
        ];
        $config = new Config($data);
        $parser = new JsonMap($config, new NullLogger());
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
        $data = [
            'mappings' => [
                'first' => [
                    'obj' => [
                        'mapping' => [
                            'destination' => 'col'
                        ]
                    ]
                ]
            ],
            'jobs' => [['endpoint' => 'first']]
        ];
        $config = new Config($data);
        $parser = new JsonMap($config, new NullLogger());
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
        $configFirst = new JobConfig([
            'endpoint' => '1st',
            'dataType' => 'first'
        ]);

        $configTags = new JobConfig([
            'endpoint' => '2nd',
            'dataType' => 'tags'
        ]);
        $data = [
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
            ],
            'jobs' => [['endpoint' => 'first']]
        ];

        $config = new Config($data);
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

        $parser = new JsonMap($config, new NullLogger());
        $parser->process($firstData, $configFirst->getDataType());
        $parser->process($secondData, $configTags->getDataType());

        self::assertEquals(
            [
                '"user","tag"' . "\n",
                '"asd","tag1"' . "\n",
                '"asd","tag2"' . "\n",
                '"asd","tag3"' . "\n",
                '"asd","tag4"' . "\n"
            ],
            file($parser->getResults()['tags'])
        );
    }

    public function testMappingSimpleArrayToTable()
    {
        $data = [
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
            ],
            'jobs' => [['endpoint' => 'reports']]
        ];
        $config = new Config($data);
        $parser = new JsonMap($config, new NullLogger());
        $data = json_decode('[{
            "rows": [
                ["2017-05-27","1234","article-bot-lef-x","83008"],
                ["2017-05-27","5678","article-bot-mob-x","105723"]
            ]
        }]');

        $parser->process($data, 'reports');

        $expected = [
            '"date","unit_id","unit_name","clicks","reports_pk"' . "\n",
            '"2017-05-27","1234","article-bot-lef-x","83008","9568b51020c31f6e4e11f43ea8093967"' . "\n",
            '"2017-05-27","5678","article-bot-mob-x","105723","9568b51020c31f6e4e11f43ea8093967"' . "\n"
        ];

        self::assertEquals($expected, file($parser->getResults()['report-rows']));
    }
}
