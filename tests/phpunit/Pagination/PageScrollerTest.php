<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use stdClass;

/**
 * @todo test with no limit until empty response
 */
class PageScrollerTest extends ExtractorTestCase
{
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

        $scroller = new PageScroller(['limit' => 500], $this->logger);

        $req = $scroller->getFirstRequest($client, $config);

        $expectedCfg = $config->getConfig();
        $expectedCfg['params']['page'] = 1;
        $expectedCfg['params']['limit'] = 500;
        self::assertEquals($client->createRequest($expectedCfg), $req);
    }

    public function testGetFirstRequestExplicit(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
            ],
        ]);

        $scroller = new PageScroller(['limit' => 500, 'firstPage' => 0], $this->logger);

        $req = $scroller->getFirstRequest($client, $config);

        $expectedCfg = $config->getConfig();
        $expectedCfg['params']['page'] = 0;
        $expectedCfg['params']['limit'] = 500;
        self::assertEquals($client->createRequest($expectedCfg), $req);
    }

    public function testGetFirstRequestNoParams(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
            ],
        ]);

        $scroller = new PageScroller([
            'firstPageParams' => false,
        ], $this->logger);

        $req = $scroller->getFirstRequest($client, $config);
        self::assertEquals($client->createRequest($config->getConfig()), $req);
    }

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

        $scroller = new PageScroller([], $this->logger);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'page' => 2,
            ],
        ]);
        self::assertEquals($expected, $next);

        $next2 = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected2 = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'page' => 3,
            ],
        ]);
        self::assertEquals($expected2, $next2);

        // Empty response
        $responseUnderLimit = new stdClass();
        $responseUnderLimit->data = [];
        $next3 = $scroller->getNextRequest($client, $config, $responseUnderLimit, $responseUnderLimit->data);
        self::assertEquals(false, $next3);
        self::assertLoggerContains('Pagination stopped, response is empty.', 'info');

        // Scroller limit higher than response size
        $scrollerLimit = new PageScroller(['pageParam' => 'page', 'limit' => 100], $this->logger);
        $next4 = $scrollerLimit->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals(false, $next4);
        self::assertLoggerContains('Pagination stopped, response is smaller than limit.', 'info');
    }

    public function testGetNextRequestPost(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
            ],
            'method' => 'POST',
        ]);

        $scroller = new PageScroller([], $this->logger);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'page' => 2,
            ],
            'method' => 'POST',
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetFirstNext(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig(['endpoint' => 'test']);

        $scroller = new PageScroller([], $this->logger);

        /** @var RestRequest $first */
        $first = $scroller->getFirstRequest($client, $config);
        /** @var RestRequest $second */
        $second = $scroller->getNextRequest($client, $config, new stdClass, ['item']);
        /** @var RestRequest $third */
        $third = $scroller->getNextRequest($client, $config, new stdClass, ['item']);
        /** @var RestRequest $last */
        $last = $scroller->getNextRequest($client, $config, new stdClass, []);

        self::assertEquals(1, $first->getParams()['page']);
        self::assertEquals(2, $second->getParams()['page']);
        self::assertEquals(3, $third->getParams()['page']);
        self::assertNull($last);
        self::assertLoggerContains('Pagination stopped, response is empty.', 'info');
    }

    public function testSetState(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig(['endpoint' => 'test']);

        $scroller = new PageScroller(['pageParam' => 'p'], $this->logger);

        $scroller->getFirstRequest($client, $config);
        $scroller->getNextRequest($client, $config, new stdClass, ['item']);

        $state = $scroller->getState();
        $newScroller = new PageScroller([], $this->logger);
        $newScroller->setState($state);
        /** @var RestRequest $third */
        $third = $newScroller->getNextRequest($client, $config, new stdClass, ['item']);
        self::assertEquals(3, $third->getParams()['p']);
    }
}
