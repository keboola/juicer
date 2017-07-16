<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\NoScroller;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class NoScrollerTest extends TestCase
{
    public function testGetNextRequest()
    {
        $client = new RestClient(new NullLogger());
        $config = new JobConfig([
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

        self::assertEquals(false, $next);
    }

    public function testGetFirstRequest()
    {
        $client = new RestClient(new NullLogger());
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);

        $scroller = new NoScroller();
        $req = $scroller->getFirstRequest($client, $config);
        $expected = $client->createRequest($config->getConfig());
        self::assertEquals($expected, $req);
    }

    public function testState()
    {
        $scroller = new NoScroller();
        self::assertEquals([], $scroller->getState());
        $scroller->setState(['foo' => 'bar']);
        self::assertEquals([], $scroller->getState());
        $scroller->reset();
        self::assertEquals([], $scroller->getState());
    }
}
