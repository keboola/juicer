<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\CursorScroller;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CursorScrollerTest extends TestCase
{
    public function testGetNextRequest()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new CursorScroller(['idKey' => 'id', 'param' => 'max_id', 'increment' => -1, 'reverse' => true]);

        $response = [
            (object) ['id' => 3],
            (object) ['id' => 2]
        ];

        $first = $scroller->getNextRequest($client, $config, $response, $response);
        $next = $scroller->getNextRequest($client, $config, $response, $response);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'max_id' => 1
            ]
        ]);
        self::assertEquals($expected, $first);
        self::assertEquals($expected, $next);

        $emptyResponse = [];
        $last = $scroller->getNextRequest($client, $config, $emptyResponse, $emptyResponse);
        self::assertFalse($last);
    }

    public function testGetNextRequestNested()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new CursorScroller(['idKey' => 'id.int', 'param' => 'since_id']);

        $response = [
            (object) [
                'id' => (object) [
                    'int' => 3
                ]
            ]
        ];

        $next = $scroller->getNextRequest($client, $config, $response, $response);
        $expected = $client->createRequest([
            'endpoint' => 'test',
            'params' => [
                'since_id' => 3
            ]
        ]);
        self::assertEquals($expected, $next);
    }

    public function testInvalid()
    {
        try {
            CursorScroller::create([]);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Missing \'pagination.idKey\' attribute required for cursor pagination', $e->getMessage());
        }
        try {
            CursorScroller::create(['idKey' => 'foo']);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Missing \'pagination.param\' attribute required for cursor pagination', $e->getMessage());
        }
    }

    public function testInvalidScroll()
    {
        $client = RestClient::create(new NullLogger());
        $config = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new CursorScroller(['idKey' => 'id', 'param' => 'max_id', 'increment' => 1]);

        $response = [
            (object) ['id' => 'foo'],
            (object) ['id' => 'bar']
        ];

        try {
            $scroller->getNextRequest($client, $config, $response, $response);
            self::fail("Must raise exception.");
        } catch (UserException $e) {
            self::assertContains('Trying to increment a pointer that is not numeric.', $e->getMessage());
        }
    }
}
