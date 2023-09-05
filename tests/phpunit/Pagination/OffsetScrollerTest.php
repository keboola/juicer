<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use stdClass;

class OffsetScrollerTest extends ExtractorTestCase
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

        $scroller = new OffsetScroller(
            ['limit' => 10, 'limitParam' => 'max', 'offsetParam' => 'startAt'],
            $this->logger,
        );

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'max' => 10,
                'startAt' => 10,
            ],
        ]);
        self::assertEquals($expected, $next);

        $next2 = $scroller->getNextRequest($client, $config, $response, $response->data);
        $expected2 = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'a' => 1,
                'b' => 2,
                'max' => 10,
                'startAt' => 20,
            ],
        ]);
        self::assertEquals($expected2, $next2);

        $responseUnderLimit = new stdClass();
        $responseUnderLimit->data = array_fill(0, 5, (object) ['key' => 'value']);
        $next3 = $scroller->getNextRequest($client, $config, $responseUnderLimit, $responseUnderLimit->data);
        self::assertEquals(false, $next3);

        // this should be in a separate testReset()
        // must match the first one, because #3 should reset the scroller
        $next4 = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($expected, $next4);
        self::assertLoggerContains('Offset limit reached, stopping scrolling.', 'info');
    }

    public function testStringParams(): void
    {

        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'startAt' => '3',
                'customLimit' => '10',
            ],
        ]);
        $limit = 10;

        $scroller = new OffsetScroller([
            'limit' => $limit,
            'offsetFromJob' => true,
            'offsetParam' => 'startAt',
            'limitParam' => 'customLimit',
        ], $this->logger);

        /** @var RestRequest $req */
        $req = $scroller->getFirstRequest($client, $config);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'startAt' => 3,
                'customLimit' => 10,
            ],
        ]);
        self::assertEquals($expected, $req);
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
        $limit = 10;

        $scroller = new OffsetScroller(['limit' => $limit], $this->logger);
        $req = $scroller->getFirstRequest($client, $config);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => array_merge(
                $config->getParams(),
                [
                    'limit' => $limit,
                    'offset' => 0,
                ],
            ),
        ]);
        self::assertEquals($expected, $req);

        $noParamsScroller = new OffsetScroller([
            'limit' => $limit,
            'limitParam' => 'count',
            'offsetParam' => 'first',
            'firstPageParams' => false,
        ], $this->logger);
        $noParamsRequest = $noParamsScroller->getFirstRequest($client, $config);
        $noParamsExpected = $client->createRequest($config->getConfig());
        self::assertEquals($noParamsExpected, $noParamsRequest);
    }

    public function testOffsetFromJob(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'startAt' => 3,
            ],
        ]);
        $limit = 10;

        $scroller = new OffsetScroller([
            'limit' => $limit,
            'offsetFromJob' => true,
            'offsetParam' => 'startAt',
        ], $this->logger);

        /** @var RestRequest $first */
        $first = $scroller->getFirstRequest($client, $config);

        self::assertEquals($config->getParams()['startAt'], $first->getParams()['startAt']);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        /** @var RestRequest $second */
        $second = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($config->getParams()['startAt'] + $limit, $second->getParams()['startAt']);
    }

    public function testLimitFromJob(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $limit = 10;
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'startAt' => 3,
                'limit' => $limit,
            ],
        ]);

        $scroller = new OffsetScroller([
            'limit' => 5,
            'offsetFromJob' => true,
            'offsetParam' => 'startAt',
        ], $this->logger);

        /** @var RestRequest $first */
        $first = $scroller->getFirstRequest($client, $config);

        self::assertEquals($config->getParams()['startAt'], $first->getParams()['startAt']);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        /** @var RestRequest $second */
        $second = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($config->getParams()['startAt'] + $limit, $second->getParams()['startAt']);
    }

    public function testLimitStringValue(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $limit = 10;
        $config = new JobConfig([
            'endpoint' => 'test',
            'params' => [
                'startAt' => 3,
                'limit' => (string) $limit,
            ],
        ]);

        $scroller = new OffsetScroller([
            'limit' => '5',
            'offsetFromJob' => true,
            'offsetParam' => 'startAt',
        ], $this->logger);

        /** @var RestRequest $first */
        $first = $scroller->getFirstRequest($client, $config);

        self::assertEquals($config->getParams()['startAt'], $first->getParams()['startAt']);

        $response = new stdClass();
        $response->data = array_fill(0, 10, (object) ['key' => 'value']);

        /** @var RestRequest $second */
        $second = $scroller->getNextRequest($client, $config, $response, $response->data);
        self::assertEquals($config->getParams()['startAt'] + $limit, $second->getParams()['startAt']);
    }


    public function testMissingLimit(): void
    {
        try {
            new OffsetScroller([], $this->logger);
            self::fail('Must cause exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Missing \'pagination.limit\' attribute required for offset pagination',
                $e->getMessage(),
            );
        }
    }

    public function testNotNumericLimit(): void
    {
        try {
            new OffsetScroller(['limit' => 'foo'], $this->logger);
            self::fail('Must cause exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Parameter \'pagination.limit\' is not numeric. Value \'"foo"\'.',
                $e->getMessage(),
            );
        }
    }
}
