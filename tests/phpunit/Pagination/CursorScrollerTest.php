<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\CursorScroller;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use PHPUnit\Framework\TestCase;

class CursorScrollerTest extends TestCase
{
    public function testGetNextRequest(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new CursorScroller(['idKey' => 'id', 'param' => 'max_id', 'increment' => -1, 'reverse' => true]);

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
    }

    public function testGetNextRequestNested(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new CursorScroller(['idKey' => 'id.int', 'param' => 'since_id']);

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
            new CursorScroller([]);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Missing \'pagination.idKey\' attribute required for cursor pagination',
                $e->getMessage()
            );
        }
        try {
            new CursorScroller(['idKey' => 'foo']);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString(
                'Missing \'pagination.param\' attribute required for cursor pagination',
                $e->getMessage()
            );
        }
        new CursorScroller(['idKey' => 'foo', 'param' => 'bar']);
    }

    public function testInvalidScroll(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $config = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new CursorScroller(['idKey' => 'id', 'param' => 'max_id', 'increment' => 1]);

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
