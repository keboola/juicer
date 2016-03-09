<?php

use Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\CursorScroller;

class CursorScrollerTest extends ExtractorTestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create();
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
}
