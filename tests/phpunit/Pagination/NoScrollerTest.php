<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use PHPUnit\Framework\TestCase;

class NoScrollerTest extends TestCase
{
    public function testGetNextRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
            ],
        ]);

        $scroller = new NoScroller();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);

        self::assertEquals(false, $next);
    }

    public function testGetFirstRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
            ],
        ]);

        $scroller = new NoScroller();
        $req = $scroller->getFirstRequest($client, $config);
        $expected = $client->createRequest($config->getConfig());
        self::assertEquals($expected, $req);
    }

    public function testState(): void
    {
        $scroller = new NoScroller();
        self::assertEquals([], $scroller->getState());
        $scroller->setState(['foo' => 'bar']);
        self::assertEquals([], $scroller->getState());
        $scroller->reset();
        self::assertEquals([], $scroller->getState());
    }
}
