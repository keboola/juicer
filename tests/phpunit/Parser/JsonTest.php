<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Parser;

use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

class JsonTest extends ExtractorTestCase
{
    public function testProcess(): void
    {
        $parser = new Json(new NullLogger(), []);

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

        self::assertEquals(
            '"pk","arr","parent"
"1","test_2901753343d19a32b8cd49e31aab748c","iAreId"
"2","test_5e36066fa62399eedd858f5e374c0c21","iAreId"
',
            file_get_contents((string) $parser->getResults()['test']->getPathName()),
        );

        self::assertEquals(
            '"data","JSON_parentId"
"1","test_2901753343d19a32b8cd49e31aab748c"
"2","test_2901753343d19a32b8cd49e31aab748c"
"3","test_2901753343d19a32b8cd49e31aab748c"
"a","test_5e36066fa62399eedd858f5e374c0c21"
"b","test_5e36066fa62399eedd858f5e374c0c21"
"c","test_5e36066fa62399eedd858f5e374c0c21"
',
            file_get_contents((string) $parser->getResults()['test_arr']->getPathName()),
        );
    }

    public function testGetMetadata(): void
    {
        $parser = new Json(new NullLogger(), []);

        $data = [
            (object) ['id' => 1],
        ];

        $parser->process($data, 'metadataTest');

        self::assertEquals(
            [
                'json_parser.struct' => [
                    'data' => [
                        '_metadataTest' => [
                            '[]' => [
                                '_id' => [
                                    'nodeType' => 'scalar',
                                    'headerNames' => 'id',
                                ],
                                'nodeType' => 'object',
                                'headerNames' => 'data',
                            ],
                            'nodeType' => 'array',
                        ],
                    ],
                    'parent_aliases' => [
                    ],
                ],
                'json_parser.structVersion' => 3,
            ],
            $parser->getMetadata(),
        );
    }

    public function testLoadMetadata(): void
    {
        $metadata = [
            'json_parser.struct' => [
                'data' => [
                    '_metadataTest' => [
                        '[]' => [
                            '_column' => [
                                'nodeType' => 'scalar',
                                'headerNames' => 'column',
                            ],
                            'nodeType' => 'object',
                            'headerNames' => 'data',
                        ],
                        'nodeType' => 'array',
                    ],
                ],
                'parent_aliases' => [
                ],
            ],
            'json_parser.structVersion' => 3,
        ];
        $parser = new Json(new NullLogger(), $metadata);

        $data = [
            (object) ['id' => 1],
        ];

        $parser->process($data, 'metadataTest');

        self::assertEquals(
            [
                'json_parser.struct' => [
                    'data' => [
                        '_metadataTest' => [
                            '[]' => [
                                '_id' => [
                                    'nodeType' => 'scalar',
                                    'headerNames' => 'id',
                                ],
                                '_column' => [
                                    'nodeType' => 'scalar',
                                    'headerNames' => 'column',
                                ],
                                'nodeType' => 'object',
                                'headerNames' => 'data',
                            ],
                            'nodeType' => 'array',
                        ],
                    ],
                    'parent_aliases' => [
                    ],
                ],
                'json_parser.structVersion' => 3,
            ],
            $parser->getMetadata(),
        );
    }

    public function testProcessNoData(): void
    {
        $logHandler = new TestHandler();
        $logger = new Logger('test', [$logHandler]);
        $parser = new Json($logger, []);

        $parser->process([], 'empty');
        self::assertTrue($logHandler->hasDebug("No data returned in 'empty'"));
    }

    public function testStructConflict(): void
    {
        $json = [
            'json_parser.struct' => [
                'root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        '_id' => [
                            'nodeType' => 'scalar',
                        ],
                        '_some_property' => [
                            'nodeType' => 'scalar',
                        ],
                        '_some.property' => [
                            'nodeType' => 'scalar',
                        ],
                    ],
                ],
            ],
            'json_parser.structVersion' => 3,
        ];
        $handler = new TestHandler();
        $logger = new Logger('null', [$handler]);
        $parser = new Json($logger, $json);
        $parser->process(
            [
                (object) [
                    'id' => 1,
                    'some_property' => 'first_value',
                    'some.property' => 'second_value',
                ],
            ],
            'root',
        );

        self::assertEquals(
            "\"id\",\"some_property\",\"some_property_u0\"\n" .
            "\"1\",\"first_value\",\"second_value\"\n",
            file_get_contents((string) $parser->getResults()['root']->getPathName()),
        );
        self::assertFalse($handler->hasWarning(
            'Using legacy JSON parser, because it is in configuration state.',
        ));
    }

    public function testNoStructExplicitVersionConflict(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('null', [$handler]);
        $parser = new Json($logger, []);
        $parser->process(
            [
                (object) [
                    'id' => 1,
                    'some_property' => 'first_value',
                    'some.property' => 'second_value',
                ],
            ],
            'root',
        );

        self::assertEquals(
            "\"id\",\"some_property\",\"some_property_u0\"\n" .
            "\"1\",\"first_value\",\"second_value\"\n",
            file_get_contents((string) $parser->getResults()['root']->getPathName()),
        );
        self::assertFalse($handler->hasWarning(
            'Using legacy JSON parser, because it has been explicitly requested.',
        ));
    }
}
