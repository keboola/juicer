<?php

declare(strict_types=1);

/**
 * @author Erik Zigo <erik.zigo@keboola.com>
 */

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Pagination\ZendeskResponseUrlScroller;
use Psr\Log\NullLogger;

class ZendeskResponseUrlScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequestStop(): void
    {
        $now = new \DateTime();
        $pagingStart = clone $now;

        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next_page']);

        for ($i = 0; $i < 4; $i++) {
            $step = round(ZendeskResponseUrlScroller::NEXT_PAGE_FILTER_MINUTES * 0.5);
            $pagingStart->modify(sprintf('-%d minutes', $step));

            $response = new \stdClass();
            $response->data = array_fill(0, 10, (object) ['key' => 'value']);
            $response->next_page = 'test?start_time=' . $pagingStart->getTimestamp();

            $next = $scroller->getNextRequest($client, $config, $response, $response->data);

            if (!$i) {
                self::assertNull($next);
            } else {
                if (!$next instanceof RestRequest) {
                    self::fail('ZendeskResponseUrlScroller::getNextRequest should return new RestRequest');
                }
            }
        }
    }

    public function testGetNextRequest(): void
    {
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next']);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->next = 'test?page=2';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?page=2',
        ]);
        self::assertEquals($expected, $next);

        $responseLast = new \stdClass();
        $responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        self::assertEquals(false, $last);
    }

    public function testGetNextRequestNested(): void
    {
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'pagination.next']);

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
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->next = 'test?page=2';

        $scroller = new ZendeskResponseUrlScroller(['urlKey' => 'next', 'includeParams' => true]);

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
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $response = (object) [
            'data' => [],
            'scroll' => '?page=2&q=v',
        ];

        $scroller = new ZendeskResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true,
        ]);

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
        $client = new RestClient(new NullLogger());
        $config = $this->getConfig();

        $response = (object) [
            'data' => [],
            'scroll' => '?page=2&b=v',
        ];

        $scroller = new ZendeskResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true,
            'includeParams' => true,
        ]);

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
