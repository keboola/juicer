<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Pagination\ResponseUrlScroller;
use Psr\Log\NullLogger;

class ResponseUrlScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseUrlScroller(['urlKey' => 'next']);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->next = 'test?page=2';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2'
        ]);
        self::assertEquals($expected, $next);

        $responseLast = new \stdClass();
        $responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        self::assertEquals(false, $last);
    }

    public function testGetNextRequestNested()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseUrlScroller(['urlKey' => 'pagination.next']);

        $response = (object) [
            'pagination' => (object) [
                'next' => 'test?page=2',
                'prev' => 'test?page=0' // Not used, just for usecase demo
            ]
        ];

        $next = $scroller->getNextRequest($client, $config, $response, []);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2'
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestParams()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->next = 'test?page=2';

        $scroller = new ResponseUrlScroller(['urlKey' => 'next', 'includeParams' => true]);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestQuery()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $response = (object) [
            'data' => [],
            'scroll' => '?page=2&q=v'
        ];

        $scroller = new ResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true
        ]);

        $nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'page' => 2,
                'q' => 'v'
            ]
        ]);
        self::assertEquals($expected, $nextRequest);
    }

    public function testGetNextRequestQueryParams()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $response = (object) [
            'data' => [],
            'scroll' => '?page=2&b=v'
        ];

        $scroller = new ResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true,
            'includeParams' => true
        ]);

        $nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'page' => 2,
                'a' => 1,
                'b' => 'v'
            ]
        ]);
        self::assertEquals($expected, $nextRequest);
    }
}
