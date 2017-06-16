<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\PageScroller;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @todo test with no limit until empty response
 */
class PageScrollerTest extends TestCase
{
    public function testGetFirstRequest()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);

        $scroller = PageScroller::create(['limit' => 500]);

        $req = $scroller->getFirstRequest($client, $config);

        $expectedCfg = $config->getConfig();
        $expectedCfg['params']['page'] = 1;
        $expectedCfg['params']['limit'] = 500;
        self::assertEquals($client->createRequest($expectedCfg), $req);
    }

    public function testGetFirstRequestExplicit()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);

        $scroller = PageScroller::create(['limit' => 500, 'firstPage' => 0]);

        $req = $scroller->getFirstRequest($client, $config);

        $expectedCfg = $config->getConfig();
        $expectedCfg['params']['page'] = 0;
        $expectedCfg['params']['limit'] = 500;
        self::assertEquals($client->createRequest($expectedCfg), $req);
    }

    public function testGetFirstRequestNoParams()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);

        $scroller = PageScroller::create([
            'firstPageParams' => false
        ]);

        $req = $scroller->getFirstRequest($client, $config);
        self::assertEquals($client->createRequest($config->getConfig()), $req);
    }

    public function testGetNextRequest()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);

        $scroller = new PageScroller([]);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'page' => 2
            ]
        ]);
        self::assertEquals($expected, $next);

        $next2 = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected2 = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'page' => 3
            ]
        ]);
        self::assertEquals($expected2, $next2);

        // Empty response
        $responseUnderLimit = new \stdClass();
        $responseUnderLimit->data = [];
        $next3 = $scroller->getNextRequest($client, $config, $responseUnderLimit, $responseUnderLimit->data);
        self::assertEquals(false, $next3);

        // Scroller limit higher than response size
        $scrollerLimit = new PageScroller(['pageParam' => 'page', 'limit' => 100]);
        $next4 = $scrollerLimit->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals(false, $next4);
    }

    public function testGetNextRequestPost()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2
            ],
            'method' => 'POST'
        ]);

        $scroller = new PageScroller([]);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'page' => 2
            ],
            'method' => 'POST'
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetFirstNext()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', ['endpoint' => 'test']);

        $scroller = new PageScroller([]);

        $first = $scroller->getFirstRequest($client, $config);
        $second = $scroller->getNextRequest($client, $config, new \stdClass, ['item']);
        $third = $scroller->getNextRequest($client, $config, new \stdClass, ['item']);
        $last = $scroller->getNextRequest($client, $config, new \stdClass, []);

        self::assertEquals(1, $first->getParams()['page']);
        self::assertEquals(2, $second->getParams()['page']);
        self::assertEquals(3, $third->getParams()['page']);
        self::assertFalse($last);
    }

    public function testSetState()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', ['endpoint' => 'test']);

        $scroller = new PageScroller(['pageParam' => 'p']);

        $scroller->getFirstRequest($client, $config);
        $scroller->getNextRequest($client, $config, new \stdClass, ['item']);

        $state = $scroller->getState();
        $newScroller = new PageScroller([]);
        $newScroller->setState($state);
        $third = $newScroller->getNextRequest($client, $config, new \stdClass, ['item']);
        self::assertEquals(3, $third->getParams()['p']);
    }
}
