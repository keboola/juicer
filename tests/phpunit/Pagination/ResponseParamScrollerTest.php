<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\ResponseParamScroller;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use stdClass;

class ResponseParamScrollerTest extends ResponseScrollerTestCase
{
    public function testGetNextRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
        ], $this->logger);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'scroll_id' => 'asdf',
            ],
        ]);
        self::assertEquals($expected, $next);

        $responseLast = new stdClass();
        $responseLast->data = array_fill(0, 10, (object) ['key' => 'value']);

        $last = $scroller->getNextRequest($client, $config, $responseLast, $responseLast->data);
        self::assertEquals(false, $last);
        self::assertLoggerContains('No more pages found, stopping pagination.', 'info');
    }

    public function testGetNextRequestNested(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => 'scroll.id',
            'queryParam' => 'scroll_id',
        ], $this->logger);

        $response = (object) [
            'scroll' => (object) [
                'id' => 'asdf',
            ],
        ];

        $next = $scroller->getNextRequest($client, $config, $response, []);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'scroll_id' => 'asdf',
            ],
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestOverride(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
            'includeParams' => false,
            'scrollRequest' => [
                'endpoint' => '_search/scroll',
                'method' => 'POST',
                'params' => [
                    'scroll' => '1m',
                ],
            ],
        ], $this->logger);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => '_search/scroll',
            'params' => [
                'scroll' => '1m',
                'scroll_id' => 'asdf',
            ],
            'method' => 'POST',
        ]);
        self::assertEquals($expected, $next);
    }

    public function testGetNextRequestParams(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);
        $response->_scroll_id = 'asdf';

        $scrollerParams = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
            'includeParams' => true,
            'scrollRequest' => [
                'params' => [
                    'scroll' => '1m',
                ],
            ],
        ], $this->logger);

        $nextParams = $scrollerParams->getNextRequest($client, $config, $response, $response->data);
        $expectedParams = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'scroll' => '1m',
                'scroll_id' => 'asdf',
            ],
        ]);
        self::assertEquals($expectedParams, $nextParams);
    }

    public function testGetFirstRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = $this->getConfig();

        $scroller = new ResponseParamScroller([
            'responseParam' => '_scroll_id',
            'queryParam' => 'scroll_id',
            'includeParams' => false,
            'scrollRequest' => [
                'endpoint' => '_search/scroll',
                'method' => 'POST',
                'params' => [
                    'scroll' => '1m',
                ],
            ],
        ], $this->logger);

        $expected = $client->createRequest($config->getConfig());
        self::assertEquals($expected, $scroller->getFirstRequest($client, $config));
    }

    public function testInvalid(): void
    {
        try {
            new ResponseParamScroller([], $this->logger);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Missing required \'pagination.responseParam\' parameter',
                $e->getMessage(),
            );
        }
        try {
            new ResponseParamScroller(['responseParam' => 'foo'], $this->logger);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Missing required \'pagination.queryParam\' parameter',
                $e->getMessage(),
            );
        }
        new ResponseParamScroller(['responseParam' => 'foo', 'queryParam' => 'bar'], $this->logger);
    }
}
