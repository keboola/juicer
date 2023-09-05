<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\CursorScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Juicer\Tests\RestClientMockBuilder;

class CursorScrollerTest extends ExtractorTestCase
{
    public function testGetNextRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new CursorScroller(
            ['idKey' => 'id', 'param' => 'max_id', 'increment' => -1, 'reverse' => true],
            $this->logger,
        );

        $response = [
            (object) ['id' => 3],
            (object) ['id' => 2],
        ];

        $first = $scroller->getNextRequest($client, $config, $response, $response);
        $next = $scroller->getNextRequest($client, $config, $response, $response);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'max_id' => 1,
            ],
        ]);
        self::assertEquals($expected, $first);
        self::assertEquals($expected, $next);

        $emptyResponse = [];
        $last = $scroller->getNextRequest($client, $config, $emptyResponse, $emptyResponse);
        self::assertNull($last);
        self::assertLoggerContains('No data in response, stopping scrolling.', 'info');
    }

    public function testGetNextRequestNested(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new CursorScroller(['idKey' => 'id.int', 'param' => 'since_id'], $this->logger);

        $response = [
            (object) [
                'id' => (object) [
                    'int' => 3,
                ],
            ],
        ];

        $next = $scroller->getNextRequest($client, $config, $response, $response);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'since_id' => 3,
            ],
        ]);
        self::assertEquals($expected, $next);
    }

    public function testInvalid(): void
    {
        try {
            new CursorScroller([], $this->logger);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Missing \'pagination.idKey\' attribute required for cursor pagination',
                $e->getMessage(),
            );
        }
        try {
            new CursorScroller(['idKey' => 'foo'], $this->logger);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Missing \'pagination.param\' attribute required for cursor pagination',
                $e->getMessage(),
            );
        }
        new CursorScroller(['idKey' => 'foo', 'param' => 'bar'], $this->logger);
    }

    public function testInvalidScroll(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new CursorScroller(['idKey' => 'id', 'param' => 'max_id', 'increment' => 1], $this->logger);

        $response = [
            (object) ['id' => 'foo'],
            (object) ['id' => 'bar'],
        ];

        try {
            $scroller->getNextRequest($client, $config, $response, $response);
            self::fail('Must raise exception.');
        } catch (UserException $e) {
            self::assertStringContainsString('Cursor value \'"foo"\' is not numeric.', $e->getMessage());
        }
    }
}
