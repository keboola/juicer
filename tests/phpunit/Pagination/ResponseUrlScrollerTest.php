<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Pagination\ResponseUrlScroller;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use stdClass;

class ResponseUrlScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ResponseUrlScroller(['urlKey' => 'next'], $this->logger);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->next = 'test?page=2';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2',
        ]);
        self::assertEquals($expected, $next);

        $responseLast = new stdClass();
        $responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        self::assertEquals(false, $last);
        self::assertLoggerContains('No more pages, stopping pagination.', 'info');
    }

    public function testGetNextRequestNested(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ResponseUrlScroller(['urlKey' => 'pagination.next'], $this->logger);

        $response = (object) [
            'pagination' => (object) [
                'next' => 'test?page=2',
                'prev' => 'test?page=0', // Not used, just for usecase demo
            ],
        ];

        $next = $scroller->getNextRequest($client, $config, $response, []);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2',
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestParams(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->next = 'test?page=2';

        $scroller = new ResponseUrlScroller(['urlKey' => 'next', 'includeParams' => true], $this->logger);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2',
            'params' => [
                'a' => 1,
                'b' => 2,
            ],
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestQuery(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $response = (object) [
            'data' => [],
            'scroll' => '?page=2&q=v',
        ];

        $scroller = new ResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true,
        ], $this->logger);

        $nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'page' => 2,
                'q' => 'v',
            ],
        ]);
        self::assertEquals($expected, $nextRequest);
    }

    public function testGetNextRequestQueryParams(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $response = (object) [
            'data' => [],
            'scroll' => '?page=2&b=v',
        ];

        $scroller = new ResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true,
            'includeParams' => true,
        ], $this->logger);

        $nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'page' => 2,
                'a' => 1,
                'b' => 'v',
            ],
        ]);
        self::assertEquals($expected, $nextRequest);
    }


    public function testGetNextRequestDelimiterParams(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ResponseUrlScroller(['urlKey' => 'links|next', 'delimiter' => '|'], $this->logger);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->links = new stdClass();
        $response->links->next = 'test?page=2';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2',
        ]);
        self::assertEquals($expected, $next);

        $responseLast = new stdClass();
        $responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        self::assertEquals(false, $last);
        self::assertLoggerContains('No more pages, stopping pagination.', 'info');
    }
}
