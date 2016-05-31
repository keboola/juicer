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

    /**
     * Test the correct placeholder is used if two levels have identical one
     */
    public function testSamePlaceholder()
    {
        list($job, $client, $parser, $history, $jobConfig) = $this->getJob('recursive2');

        $children = $jobConfig->getChildJobs();
        $child = reset($children);

        $childJob = $this->callMethod($job, 'createChild', [
                $child,
                [0 => ['id' => 123]]
            ]
        );

        $this->assertEquals('root/123', $this->getProperty($childJob, 'config')->getEndpoint());

        $grandChildren = $child->getChildJobs();
        $grandChild = reset($grandChildren);
        $grandChildJob = $this->callMethod($childJob, 'createChild', [
                $grandChild,
                [0 => ['id' => 456], 1 => ['id' => 123]]
            ]
        );

        $this->assertEquals('root/123/456', $this->getProperty($grandChildJob, 'config')->getEndpoint());
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

        $this->assertEquals(
            [
                '1:id' => [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => 123
                ]
            ],
            $this->getProperty($childJob, 'parentParams')
        );

        $this->assertEquals('comments', $this->callMethod($childJob, 'getDataType', []));

        $grandChildren = $child->getChildJobs();
        $grandChild = reset($grandChildren);
        $grandChildJob = $this->callMethod($childJob, 'createChild', [
                $grandChild,
                [0 => ['id' => 456], 1 => ['id' => 123]]
            ]
        );

        // Ensure the IDs from 2 parent levels are properly mapped
        $values = $this->getProperty($grandChildJob, 'parentParams');
        $this->assertEquals(456, $values['id']['value']);
        $this->assertEquals(123, $values['2:id']['value']);
        // Check the dataType from endpoint has placeholders not replaced by values
        $this->assertEquals('third/level/{2:id}/{id}.json', $this->callMethod($grandChildJob, 'getDataType', []));

        $this->assertEquals('third/level/123/456.json', $this->getProperty($grandChildJob, 'config')->getEndpoint());
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
