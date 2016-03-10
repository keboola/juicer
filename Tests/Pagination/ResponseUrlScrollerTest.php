<?php

use Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\ResponseUrlScroller;

class ResponseUrlScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create();
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
        $client = RestClient::create();
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
        $client = RestClient::create();
        $config = $this->getConfig();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->next = 'test?page=2';

        $scrollerParams = new ResponseUrlScroller(['urlKey' => 'next', 'includeParams' => true]);

        $nextParams = $scrollerParams->getNextRequest($client, $config, $response, $response->data);
        $expectedParams = $client->createRequest([
            'endpoint' => 'test?page=2',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);
        self::assertEquals($expectedParams, $nextParams);
    }
}
