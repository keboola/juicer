<?php

use Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Config\Configuration,
    Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Parser\Json,
    Keboola\Juicer\Pagination\ResponseUrlScroller,
    Keboola\Juicer\Extractor\RecursiveJob,
    Keboola\Juicer\Common\Logger;

use Keboola\Json\Parser;
use Keboola\Temp\Temp;

use GuzzleHttp\Client,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Stream\Stream,
    GuzzleHttp\Subscriber\Mock,
    GuzzleHttp\Subscriber\History;

/**
 * @todo testCreateChild
 * @todo scroll (w/ reset)
 */
class RecursiveJobTest extends ExtractorTestCase
{
    public function testParse()
    {
        list($job, $client, $parser) = $this->getJob('iteration');

        $response = json_decode('{
            "data": [
                {
                    "a": "first",
                    "id": 1,
                    "c": ["jedna","one",1]
                },
                {
                    "a": "second",
                    "id": 2,
                    "c": ["dva","two",2]
                }
            ]
        }');

        $this->callMethod($job, 'parse', [$response->data, ['userData' => 'hello']]);

        self::assertEquals(
            ['tickets_export', 'tickets_export_c'],
            array_keys($parser->getResults())
        );

        self::assertFileEquals(
            __DIR__ . '/../data/recursiveJobParseResults/tickets_export',
            $parser->getResults()['tickets_export']->getPathname()
        );
        self::assertFileEquals(
            __DIR__ . '/../data/recursiveJobParseResults/tickets_export_c',
            $parser->getResults()['tickets_export_c']->getPathname()
        );
    }

    public function testSamePlaceholder()
    {
        list($job, $client, $parser, $history) = $this->getJob('recursive2');

        $cubes = '{
            "@odata.context": "$metadata#Cubes",
            "value": [
                {
                    "Name": "plan_BudgetPlan",
                    "Rules": "tl;dr"
                },
                {
                    "Name": "SDK.SampleCube",
                    "Rules": ""
                }
            ]
        }';

        $views = '{
            "@odata.context": "../$metadata#Cubes(\'plan_BudgetPlan\')/Views",
            "value": [
                {
                    "Name": "budget_placeholder",
                    "SuppressEmptyRows": false
                },
                {
                    "Name": "Budget Input Detailed",
                    "SuppressEmptyRows": false
                }
            ]
        }';

        $results = '{}';

        $mock = new Mock([
            new Response(200, [], Stream::factory($cubes)),
            new Response(200, [], Stream::factory($views)),
            new Response(200, [], Stream::factory($results)),
            new Response(200, [], Stream::factory($results)),
            new Response(200, [], Stream::factory($views)),
            new Response(200, [], Stream::factory($results)),
            new Response(200, [], Stream::factory($results)),
        ]);
        $client->getClient()->getEmitter()->attach($mock);

        $job->run();

        $urls = [];
        foreach($history as $item) {
            $urls[] = $item['request']->getUrl();
        }

        self::assertEquals(
            [
                'Cubes',
                'Cubes(\'plan_BudgetPlan\')/Views',
                'Cubes(\'plan_BudgetPlan\')/Views(\'budget_placeholder\')/tm1.Execute?%24expand=Cells',
                'Cubes(\'plan_BudgetPlan\')/Views(\'Budget%20Input%20Detailed\')/tm1.Execute?%24expand=Cells',
                'Cubes(\'SDK.SampleCube\')/Views',
                'Cubes(\'SDK.SampleCube\')/Views(\'budget_placeholder\')/tm1.Execute?%24expand=Cells',
                'Cubes(\'SDK.SampleCube\')/Views(\'Budget%20Input%20Detailed\')/tm1.Execute?%24expand=Cells',
            ],
            $urls
        );
    }

    public function testDataTypeWithId()
    {
        list($job, $client, $parser, $history, $jobConfig) = $this->getJob('recursiveNoType');

        $parentBody = '[
                {"field": "data", "id": 1},
                {"field": "more", "id": 2}
        ]';
        $detail1 = '[
                {"detail": "something", "subId": 1}
        ]';
        $detail2 = '[
                {"detail": "somethingElse", "subId": 1},
                {"detail": "another", "subId": 2}
        ]';
        $subDetail = '[{"grand": "child"}]';

        $mock = new Mock([
            new Response(200, [], Stream::factory($parentBody)),
            new Response(200, [], Stream::factory($detail1)),
            new Response(200, [], Stream::factory($subDetail)),
            new Response(200, [], Stream::factory($detail2)),
            new Response(200, [], Stream::factory($subDetail)),
            new Response(200, [], Stream::factory($subDetail))
        ]);
        $client->getClient()->getEmitter()->attach($mock);


        $job->run();

//         $children = $jobConfig->getChildJobs();
//         var_dump(reset($children)->getDataType());
        self::assertEquals(
            ['exports_tickets_json', 'tickets__1_id__comments_json', 'third_level__2_id___id__json'],
            array_keys($parser->getResults())
        );
    }

    public function testCreateChild()
    {
        list($job, $client, $parser, $history, $jobConfig) = $this->getJob('recursive');

        $children = $jobConfig->getChildJobs();
        $child = reset($children);

        $childJob = $this->callMethod($job, 'createChild', [
            $child,
            [0 => ['id' => 123]]
        ]
        );

        $parentParams = (new ReflectionClass($childJob))->getProperty('parentParams');
        $parentParams->setAccessible(true);

        $this->assertEquals(
            [
                '1:id' => [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => 123
                ]
            ],
            $parentParams->getValue($childJob)
        );

        $this->assertEquals('comments', $this->callMethod($childJob, 'getDataType', []));

        $grandChildren = $child->getChildJobs();
        $grandChild = reset($grandChildren);
        $grandChildJob = $this->callMethod($childJob, 'createChild', [
            $grandChild,
            [0 => ['id' => 456], 1 => ['id' => 123]]
        ]
        );

        $childParams = (new ReflectionClass($grandChildJob))->getProperty('parentParams');
        $childParams->setAccessible(true);
var_dump($childParams->getValue($grandChildJob));

        $this->assertEquals('third/level/{2:id}/{id}.json', $this->callMethod($grandChildJob, 'getDataType', []));
    }

    /**
     * @dataProvider placeholderProvider
     */
    public function testGetPlaceholder($field, $expectedValue)
    {
        $job = $this->getMockBuilder('Keboola\Juicer\Extractor\RecursiveJob')
            ->disableOriginalConstructor()
            ->getMock();

        $value = $this->callMethod(
            $job,
            'getPlaceholder',
            [ // $placeholder, $field, $parentResults
                '1:id',
                $field,
                [
                    (object) [
                        'field' => 'data',
                        'id' => '1:1'
                    ]
                ]
            ]
        );

        $this->assertEquals(
            [
                'placeholder' => '1:id',
                'field' => 'id',
                'value' => $expectedValue
            ],
            $value
        );
    }

    public function placeholderProvider()
    {
        return [
            [
                [
                    'path' => 'id',
                    'function' => 'urlencode',
                    'args' => [
                        ['placeholder' => 'value']
                    ]
                ],
                '1%3A1'
            ],
            [
                'id',
                '1:1'
            ]
        ];
    }

    /**
     * @dataProvider placeholderValueProvider
     */
    public function testGetPlaceholderValue($level, $expected)
    {
        $job = $this->getMockBuilder('Keboola\Juicer\Extractor\RecursiveJob')
            ->disableOriginalConstructor()
            ->getMock();

        $value = $this->callMethod(
            $job,
            'getPlaceholderValue',
            [ // $field, $parentResults, $level, $placeholder
                'id',
                [
                    0 => ['id' => 123],
                    1 => ['id' => 456]
                ],
                $level,
                '1:id'
            ]
        );

        $this->assertEquals($expected, $value);
    }

    /**
     * @dataProvider placeholderErrorValueProvider
     */
    public function testGetPlaceholderValueError($data, $message)
    {
        $job = $this->getMockBuilder('Keboola\Juicer\Extractor\RecursiveJob')
            ->disableOriginalConstructor()
            ->getMock();

        try {
            $value = $this->callMethod(
                $job,
                'getPlaceholderValue',
                [ // $field, $parentResults, $level, $placeholder
                    'id',
                    $data,
                    0,
                    '1:id'
                ]
            );
        } catch(\Keboola\Juicer\Exception\UserException $e) {
            $this->assertEquals($message, $e->getMessage());
            return;
        }

        $this->fail('UserException was not thrown');
    }

    public function placeholderErrorValueProvider()
    {
        return [
            [[], 'Level 1 not found in parent results! Maximum level: 0'],
            [[0 => ['noId' => 'noVal']], 'No value found for 1:id in parent result. (level: 1)']
        ];
    }

    public function placeholderValueProvider()
    {
        return [
            [
                0,
                123
            ],
            [
                1,
                456
            ]
        ];
    }

    /**
     * I'm not too sure this is optimal!
     * If it looks stupid, but works, it ain't stupid!
     */
    public function getJob($dir = 'recursive')
    {
        $temp = new Temp('recursion');
        $configuration = new Configuration(__DIR__ . '/../data/' . $dir, 'test', $temp);

        $jobConfig = array_values($configuration->getConfig()->getJobs())[0];

        $parser = Json::create($configuration->getConfig(), $this->getLogger('test', true), $temp);

        $client = RestClient::create();

        $history = new History();
        $client->getClient()->getEmitter()->attach($history);

        $job = $this->getMockForAbstractClass(
            'Keboola\Juicer\Extractor\RecursiveJob',
            [$jobConfig, $client, $parser]
        );

        return [
            $job,
            $client,
            $parser,
            $history,
            $jobConfig
        ];
    }
}
