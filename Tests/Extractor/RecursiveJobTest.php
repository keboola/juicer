<?php

namespace Keboola\Juicer\Tests\Extractor;

use Keboola\Juicer\Config\Configuration;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Extractor\RecursiveJob;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Temp\Temp;
use GuzzleHttp\Subscriber\History;
use Psr\Log\NullLogger;

/**
 * @todo testCreateChild
 * @todo scroll (w/ reset)
 */
class RecursiveJobTest extends ExtractorTestCase
{
    public function testParse()
    {
        /** @var Json $parser */
        list($job, $parser) = $this->getJob('simple_basic');

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

        self::callMethod($job, 'parse', [$response->data, ['userData' => 'hello']]);

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
        list($job, $parser, $jobConfig) = $this->getJob('recursive_same_ph');

        $children = $jobConfig->getChildJobs();
        $child = reset($children);

        $childJob = self::callMethod(
            $job,
            'createChild',
            [
                $child,
                [0 => ['id' => 123]]
            ]
        );

        self::assertEquals('root/123', self::getProperty($childJob, 'config')->getEndpoint());

        $grandChildren = $child->getChildJobs();
        $grandChild = reset($grandChildren);
        $grandChildJob = self::callMethod(
            $childJob,
            'createChild',
            [
                $grandChild,
                [0 => ['id' => 456], 1 => ['id' => 123]]
            ]
        );

        self::assertEquals('root/123/456', self::getProperty($grandChildJob, 'config')->getEndpoint());
    }

    public function testCreateChild()
    {
        list($job, $parser, $jobConfig) = $this->getJob('recursive');

        $children = $jobConfig->getChildJobs();
        $child = reset($children);

        $childJob = self::callMethod(
            $job,
            'createChild',
            [
                $child,
                [0 => ['id' => 123]]
            ]
        );

        self::assertEquals(
            [
                '1:id' => [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => 123
                ]
            ],
            self::getProperty($childJob, 'parentParams')
        );

        self::assertEquals('comments', self::callMethod($childJob, 'getDataType', []));

        $grandChildren = $child->getChildJobs();
        $grandChild = reset($grandChildren);
        $grandChildJob = self::callMethod(
            $childJob,
            'createChild',
            [
                $grandChild,
                [0 => ['id' => 456], 1 => ['id' => 123]]
            ]
        );

        // Ensure the IDs from 2 parent levels are properly mapped
        $values = self::getProperty($grandChildJob, 'parentParams');
        self::assertEquals(456, $values['id']['value']);
        self::assertEquals(123, $values['2:id']['value']);
        // Check the dataType from endpoint has placeholders not replaced by values
        self::assertEquals('third/level/{2:id}/{id}.json', self::callMethod($grandChildJob, 'getDataType', []));

        self::assertEquals('third/level/123/456.json', self::getProperty($grandChildJob, 'config')->getEndpoint());
    }

    /**
     * @dataProvider placeholderProvider
     * @param $field
     * @param $expectedValue
     */
    public function testGetPlaceholder($field, $expectedValue)
    {
        $job = $this->getMockBuilder(RecursiveJob::class)
            ->disableOriginalConstructor()
            ->getMock();

        $value = self::callMethod(
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

        self::assertEquals(
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
     * @param $level
     * @param $expected
     */
    public function testGetPlaceholderValue($level, $expected)
    {
        $job = $this->getMockBuilder(RecursiveJob::class)
            ->disableOriginalConstructor()
            ->getMock();

        $value = self::callMethod(
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

        self::assertEquals($expected, $value);
    }

    /**
     * @dataProvider placeholderErrorValueProvider
     * @param $data
     * @param $message
     */
    public function testGetPlaceholderValueError($data, $message)
    {
        $job = $this->getMockBuilder(RecursiveJob::class)
            ->disableOriginalConstructor()
            ->getMock();

        try {
            self::callMethod(
                $job,
                'getPlaceholderValue',
                [ // $field, $parentResults, $level, $placeholder
                    'id',
                    $data,
                    0,
                    '1:id'
                ]
            );
            self::fail('UserException was not thrown');
        } catch (UserException $e) {
            self::assertEquals($message, $e->getMessage());
        }
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
     * @param string $dir
     * @return array
     */
    public function getJob($dir)
    {
        $temp = new Temp('recursion');
        $configuration = new Configuration(__DIR__ . '/../data/' . $dir, 'test', $temp);

        $jobConfig = array_values($configuration->getConfig()->getJobs())[0];

        $parser = Json::create($configuration->getConfig(), new NullLogger(), $temp);

        $client = RestClient::create(new NullLogger());

        $history = new History();
        $client->getClient()->getEmitter()->attach($history);

        $job = $this->getMockForAbstractClass(
            RecursiveJob::class,
            [$jobConfig, $client, $parser, new NullLogger()]
        );

        return [
            $job,
            $parser,
            $jobConfig
        ];
    }
}
