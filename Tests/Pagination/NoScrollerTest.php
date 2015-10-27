<?php

use Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\NoScroller;

class NoScrollerTest extends ExtractorTestCase
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

        $scroller = new NoScroller();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);

        $this->assertEquals(false, $next);
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

        $scroller = new NoScroller();
        $req = $scroller->getFirstRequest($client, $config);
        $expected = $client->createRequest($config->getConfig());
        $this->assertEquals($expected, $req);
    }
}
