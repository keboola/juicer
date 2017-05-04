<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;

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

        $scroller = new OffsetScroller(['limit' => 10, 'limitParam' => 'max', 'offsetParam' => 'startAt']);

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
        self::assertEquals($expected, $next);

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
        self::assertEquals($expected2, $next2);

        $responseUnderLimit = new \stdClass();
        $responseUnderLimit->data = array_fill(0, 5, (object) ['key' => 'value']);
        $next3 = $scroller->getNextRequest($client, $config, $responseUnderLimit, $responseUnderLimit->data);
        self::assertEquals(false, $next3);

        // this should be in a separete testReset()
        // must match the first one, because #3 should reset the scroller
        $next4 = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($expected, $next4);
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

        $scroller = new OffsetScroller(['limit' => $limit]);
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
        self::assertEquals($expected, $req);

        $noParamsScroller = new OffsetScroller([
            'limit' => $limit,
            'limitParam' => 'count',
            'offsetParam' => 'first',
            'firstPageParams' => false
        ]);
        $noParamsRequest = $noParamsScroller->getFirstRequest($client, $config);
        $noParamsExpected = $client->createRequest($config->getConfig());
        self::assertEquals($noParamsExpected, $noParamsRequest);
    }

    public function testOffsetFromJob()
    {
        $client = RestClient::create();
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'startAt' => 3
            ]
        ]);
        $limit = 10;

        $scroller = new OffsetScroller([
            'limit' => $limit,
            'offsetFromJob' => true,
            'offsetParam' => 'startAt'
        ]);

        $first = $scroller->getFirstRequest($client, $config);

        self::assertEquals($config->getParams()['startAt'], $first->getParams()['startAt']);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $second = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($config->getParams()['startAt'] + $limit, $second->getParams()['startAt']);
    }
}
