<?php

use Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\ResponseParamScroller;

class ResponseParamScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create();
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller('_scroll_id', 'scroll_id');

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
        $this->assertEquals($expected, $next);

        $responseLast = new \stdClass();
        $responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        $this->assertEquals(false, $last);
    }

    public function testGetNextRequestOverride()
    {
        $client = RestClient::create();
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller('_scroll_id', 'scroll_id', false, [
            'endpoint' => '_search/scroll',
            'method' => 'POST',
            'params' => [
                'scroll' => '1m'
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
        $this->assertEquals($expected, $next);
    }

    public function testGetNextRequestParams()
    {
        $client = RestClient::create();
        $config = $this->getConfig();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $scrollerParams = new ResponseParamScroller('_scroll_id', 'scroll_id', true, [
            'params' => [
                'scroll' => '1m'
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
        $this->assertEquals($expectedParams, $nextParams);
    }

    public function testGetFirstRequest()
    {
        $client = RestClient::create();
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller('_scroll_id', 'scroll_id', false, [
            'endpoint' => '_search/scroll',
            'method' => 'POST',
            'params' => [
                'scroll' => '1m'
            ]
        ]);

        $expected = $client->createRequest($config->getConfig());
        $this->assertEquals($expected, $scroller->getFirstRequest($client, $config));
    }
}
