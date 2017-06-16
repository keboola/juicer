<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\CursorScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Psr\Log\NullLogger;

class CursorScrollerTest extends ExtractorTestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new CursorScroller(['idKey' => 'id', 'param' => 'max_id', 'increment' => -1, 'reverse' => true]);

        $response = [
            (object) ['id' => 3],
            (object) ['id' => 2]
        ];

        $next = $scroller->getNextRequest($client, $config, $response, $response);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'max_id' => 1
            ]
        ]);
        self::assertEquals($expected, $next);

        $emptyResponse = [];
        $last = $scroller->getNextRequest($client, $config, $emptyResponse, $emptyResponse);
        self::assertFalse($last);
    }

    public function testGetNextRequestNested()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new CursorScroller(['idKey' => 'id.int', 'param' => 'since_id']);

        $response = [
            (object) [
                'id' => (object) [
                    'int' => 3
                ]
            ]
        ];

        $next = $scroller->getNextRequest($client, $config, $response, $response);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'since_id' => 3
            ]
        ]);
        self::assertEquals($expected, $next);
    }
}
