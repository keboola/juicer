<?php

use    Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\OffsetScroller;

class OffsetScrollerTest extends ExtractorTestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create();
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);

        $scroller = new OffsetScroller(10, 'max', 'startAt');

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'max' => 10,
                'startAt' => 10
            ]
        ]);
        $this->assertEquals($expected, $next);

        $next2 = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected2 = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'max' => 10,
                'startAt' => 20
            ]
        ]);
        $this->assertEquals($expected2, $next2);

        $responseUnderLimit = new \stdClass();
        $responseUnderLimit->data = array_fill(0, 5, (object) ['key' => 'value']);
        $next3 = $scroller->getNextRequest($client, $config, $responseUnderLimit, $responseUnderLimit->data);
        $this->assertEquals(false, $next3);

        // this should be in a separete testReset()
        // must match the first one, because #3 should reset the scroller
        $next4 = $scroller->getNextRequest($client, $config, $response, $response->data);
        $this->assertEquals($expected, $next4);
    }

    public function testGetFirstRequest()
    {
        $client = RestClient::create();
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);
        $limit = 10;

        $scroller = new OffsetScroller($limit);
        $req = $scroller->getFirstRequest($client, $config);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => array_merge(
                $config->getParams(),
                [
                    OffsetScroller::DEFAULT_LIMIT_PARAM => $limit,
                    OffsetScroller::DEFAULT_OFFSET_PARAM => 0
                ]
            )
        ]);
        $this->assertEquals($expected, $req);

        $noParamsScroller = new OffsetScroller($limit, 'count', 'first', false);
        $noParamsRequest = $noParamsScroller->getFirstRequest($client, $config);
        $noParamsExpected = $client->createRequest($config->getConfig());
        $this->assertEquals($noParamsExpected, $noParamsRequest);
    }
}
