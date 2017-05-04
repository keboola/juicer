<?php
/**
 * @author Erik Zigo <erik.zigo@keboola.com>
 */

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Pagination\FacebookResponseUrlScroller;

class FacebookResponseUrlScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequestStop()
    {
        $now = new \DateTime();
        $pagingStart = clone $now;
        $pagingStart->modify('-90 days');

        $client = RestClient::create();
        $config = $this->getConfig();

        $scroller = new FacebookResponseUrlScroller([]);

        for ($i = 0; $i < 5; $i++) {
            $pagingStart->modify(sprintf('+%d days', 20));


            $response = new \stdClass();
            $response->data = array_fill(0, 10, (object)['key' => 'value']);
            $response->paging = new \stdClass();
            $response->paging->next = 'test?since=' . $pagingStart->getTimestamp();

            $next = $scroller->getNextRequest($client, $config, $response, $response->data);

            if ($i == 4) {
                $this->assertFalse($next);
            } else {
                if (!$next instanceof \Keboola\Juicer\Client\RestRequest) {
                    $this->fail('ZendeskResponseUrlScroller::getNextRequest should return new RestRequest');
                }
            }
        }
    }

    public function testGetNextRequest()
    {
        $now = new \DateTime();
        $pagingStart = clone $now;
        $pagingStart->modify('-90 days');

        $client = RestClient::create();
        $config = $this->getConfig();

        $scroller = new FacebookResponseUrlScroller([]);

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object)['key' => 'value']);
        $response->paging = new \stdClass();
        $response->paging->next = 'test?since=' . $pagingStart->getTimestamp();

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?since=' . $pagingStart->getTimestamp()
        ]);
        self::assertEquals($expected, $next);

        $responseLast = new \stdClass();
        $responseLast->data = array_fill(0, 10, (object)['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        self::assertEquals(false, $last);
    }

    public function testGetNextRequestNested()
    {
        $pagingPrev = new \DateTime();
        $pagingNext = clone $pagingPrev;

        $pagingNext->modify('-30 days');
        $pagingPrev->modify('-90 days');

        $client = RestClient::create();
        $config = $this->getConfig();

        $scroller = new FacebookResponseUrlScroller([]);

        $response = (object)[
            'paging' => (object)[
                'next' => 'test?since=' . $pagingNext->getTimestamp(),
                'prev' => 'test?since=' . $pagingPrev->getTimestamp() // Not used, just for usecase demo
            ]
        ];

        $next = $scroller->getNextRequest($client, $config, $response, []);
        $expected = $client->createRequest([
            'endpoint' => 'test?since=' . $pagingNext->getTimestamp(),
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestParams()
    {
        $pagingNext = (new \DateTime())->modify('-30 days');

        $client = RestClient::create();
        $config = $this->getConfig();

        $response = new \stdClass();
        $response->data = array_fill(0, 10, (object)['key' => 'value']);
        $response->paging = new \stdClass();
        $response->paging->next = 'test?since=' . $pagingNext->getTimestamp();

        $scroller = new FacebookResponseUrlScroller(['includeParams' => true]);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test?since=' . $pagingNext->getTimestamp(),
            'params' => [
                'a' => 1,
                'b' => 2
            ]
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestQuery()
    {
        $pagingNext = (new \DateTime())->modify('-30 days');

        $client = RestClient::create();
        $config = $this->getConfig();

        $response = (object)[
            'data' => [],
            'scroll' => '?q=v&since=' . $pagingNext->getTimestamp()
        ];

        $scroller = new FacebookResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true
        ]);

        $nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'since' => $pagingNext->getTimestamp(),
                'q' => 'v'
            ]
        ]);
        self::assertEquals($expected, $nextRequest);
    }

    public function testGetNextRequestQueryParams()
    {
        $pagingNext = (new \DateTime())->modify('-30 days');

        $client = RestClient::create();
        $config = $this->getConfig();

        $response = (object)[
            'data' => [],
            'scroll' => '?b=v&since=' . $pagingNext->getTimestamp()
        ];

        $scroller = new FacebookResponseUrlScroller([
            'urlKey' => 'scroll',
            'paramIsQuery' => true,
            'includeParams' => true
        ]);

        $nextRequest = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'since' => $pagingNext->getTimestamp(),
                'a' => 1,
                'b' => 'v'
            ]
        ]);
        self::assertEquals($expected, $nextRequest);
    }
}
