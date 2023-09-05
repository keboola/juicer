<?php

declare(strict_types=1);

/**
 * @author Erik Zigo <erik.zigo@keboola.com>
 */

namespace Keboola\Juicer\Tests\Pagination;

use DateTime;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Pagination\ZendeskResponseUrlScroller;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use stdClass;

class ZendeskResponseUrlScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequestStop(): void
    {
        $now = new DateTime();
        $pagingStart = clone $now;

        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next_page'], $this->logger);

        for ($i = 0; $i < 4; $i++) {
            $step = round(ZendeskResponseUrlScroller::NEXT_PAGE_FILTER_MINUTES * 0.5);
            $pagingStart->modify(sprintf('-%d minutes', $step));

            $response = new stdClass();
            $response->data = array_fill(0, 10, (object) ['key' => 'value']);
            $response->next_page = 'test?start_time=' . $pagingStart->getTimestamp();

            $next = $scroller->getNextRequest($client, $config, $response, $response->data);

            if (!$i) {
                self::assertNull($next);
                self::assertLoggerContains(
                    sprintf(
                        'Next page start_time "%s" is too recent, skipping...',
                        $pagingStart->format('Y-m-d H:i:s'),
                    ),
                    'info',
                );
            } else {
                if (!$next instanceof RestRequest) {
                    self::fail('ZendeskResponseUrlScroller::getNextRequest should return new RestRequest');
                }
            }
        }
    }

    public function testGetNextRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next'], $this->logger);

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
        self::assertLoggerContains('No more pages to scroll.', 'info');
    }

    public function testGetNextRequestNested(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'pagination.next'], $this->logger);

        $response = (object) [
            'pagination' => (object) [
                'next' => 'test?page=2',
                'prev' => 'test?page=0', // Not used, just for demo
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

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next', 'includeParams' => true], $this->logger);

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

        $scroller = new ZendeskResponseUrlScroller([
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

        $scroller = new ZendeskResponseUrlScroller([
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
}
