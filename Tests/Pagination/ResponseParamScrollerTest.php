<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Pagination\ResponseParamScroller;
use Psr\Log\NullLogger;

class ResponseParamScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id'
        ]);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'scroll_id' => 'asdf'
            ]
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

        $scroller = new ResponseParamScroller([
            'responseParam' => 'scroll.id',
            'queryParam' => 'scroll_id'
        ]);

        $response = (object) [
            'scroll' => (object) [
                'id' => 'asdf'
            ]
        ];

        $next = $scroller->getNextRequest($client, $config, $response, []);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'scroll_id' => 'asdf'
            ]
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestOverride()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
            'includeParams' => false,
            'scrollRequest' => [
                'endpoint' => '_search/scroll',
                'method' => 'POST',
                'params' => [
                    'scroll' => '1m'
                ]
            ]
        ]);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => '_search/scroll',
            'params' => [
                'scroll' => '1m',
                'scroll_id' => 'asdf'
            ],
            'method' => 'POST'
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestParams()
    {
        $client = RestClient::create(new NullLogger();
        $config = $this->getConfig();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $scrollerParams = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
            'includeParams' => true,
            'scrollRequest' => [
                'params' => [
                    'scroll' => '1m'
                ]
            ]
        ]);

        $nextParams = $scrollerParams->getNextRequest($client, $config, $response, $response->data);
        $expectedParams = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'scroll' => '1m',
                'scroll_id' => 'asdf'
            ]
        ]);
        self::assertEquals($expectedParams, $nextParams);
    }

    public function testGetFirstRequest()
    {
        $client = RestClient::create(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller(['responseParam' => '_scroll_id', 'queryParam' => 'scroll_id', 'includeParams' => false, 'scrollRequest' => [
            'endpoint' => '_search/scroll',
            'method' => 'POST',
            'params' => [
                'scroll' => '1m'
            ]
        ]]);

        $expected = $client->createRequest($config->getConfig());
        self::assertEquals($expected, $scroller->getFirstRequest($client, $config));
    }
}
