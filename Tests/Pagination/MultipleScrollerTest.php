<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\MultipleScroller;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MultipleScrollerTest extends TestCase
{
    public function testGetNextRequest(): void
    {
        $scroller = new MultipleScroller($this->getScrollerConfig());

        $cursorResponse = [
            (object) ['id' => 2],
            (object) ['id' => 1],
        ];

        $paramResponse = (object) [
            'next_page_id' => 'page2',
        ];

        $noScrollerResponse = (object) [
            'results' => [
                (object) ['data' => 'val1'],
                (object) ['data' => 'val2'],
            ],
        ];

        $client = new RestClient(new NullLogger());

        $paramConfig = new JobConfig([
            'endpoint' => 'structuredData',
            'scroller' => 'param',
        ]);

        $cursorConfig = new JobConfig([
            'endpoint' => 'arrData',
            'scroller' => 'cursor',
        ]);

        $noScrollerConfig = new JobConfig([
            'endpoint' => 'data',
        ]);

        $nextParam = $scroller->getNextRequest($client, $paramConfig, $paramResponse, []);
        $expectedParam = $client->createRequest([
            'endpoint' => 'structuredData',
            'params' => [
                'page_id' => 'page2',
            ],
        ]);
        self::assertEquals($expectedParam, $nextParam);

        $nextCursor = $scroller->getNextRequest($client, $cursorConfig, $cursorResponse, $cursorResponse);
        $expectedCursor = $client->createRequest([
            'endpoint' => 'arrData',
            'params' => [
                'newerThan' => 2,
            ],
        ]);
        self::assertEquals($expectedCursor, $nextCursor);

        $nextNone = $scroller->getNextRequest(
            $client,
            $noScrollerConfig,
            $noScrollerResponse,
            $noScrollerResponse->results
        );
        self::assertFalse($nextNone);
    }

    public function testGetNextRequestDefault(): void
    {
        $config = $this->getScrollerConfig();
        $config['default'] = 'cursor';
        $scroller = new MultipleScroller($config);

        $paramConfig = new JobConfig([
            'endpoint' => 'structuredData',
            'scroller' => 'param',
        ]);

        $noScrollerConfig = new JobConfig([
            'endpoint' => 'data',
        ]);

        $cursorResponse = [
            (object) ['id' => 2],
            (object) ['id' => 1],
        ];

        $paramResponse = (object) [
            'next_page_id' => 'page2',
        ];

        $client = new RestClient(new NullLogger());

        $nextParam = $scroller->getNextRequest($client, $paramConfig, $paramResponse, []);
        $expectedParam = $client->createRequest([
            'endpoint' => 'structuredData',
            'params' => [
                'page_id' => 'page2',
            ],
        ]);
        self::assertEquals($expectedParam, $nextParam);

        $nextCursor = $scroller->getNextRequest($client, $noScrollerConfig, $cursorResponse, $cursorResponse);
        $expectedCursor = $client->createRequest([
            'endpoint' => 'data',
            'params' => [
                'newerThan' => 2,
            ],
        ]);
        self::assertEquals($expectedCursor, $nextCursor);
    }

    public function testGetNextRequestDefaultException(): void
    {
        $config = $this->getScrollerConfig();
        $config['default'] = 'def';
        $scroller = new MultipleScroller($config);

        $noScrollerConfig = new JobConfig([
            'endpoint' => 'data',
        ]);

        $scroller->getFirstRequest(new RestClient(new NullLogger()), $noScrollerConfig);
    }

    public function testUndefinedScrollerException(): void
    {
        $config = $this->getScrollerConfig();
        $scroller = new MultipleScroller($config);

        $noScrollerConfig = new JobConfig([
            'endpoint' => 'data',
            'scroller' => 'nonExistentScroller',
        ]);

        $scroller->getFirstRequest(new RestClient(new NullLogger()), $noScrollerConfig);
    }

    protected function getScrollerConfig()
    {
        $responseParamConfig = [
            'method' => 'response.param',
            'responseParam' => 'next_page_id',
            'queryParam' => 'page_id',
        ];

        $cursorConfig = [
            'method' => 'cursor',
            'idKey' => 'id',
            'param' => 'newerThan',
        ];

        $pageConfig = [
            'method' => 'pagenum',
        ];

        return [
            'scrollers' => [
                'param' => $responseParamConfig,
                'cursor' => $cursorConfig,
                'page' => $pageConfig,
            ],
        ];
    }

    public function testInvalid(): void
    {
        try {
            new MultipleScroller([]);
            self::fail('Invalid config must raise exception.');
        } catch (UserException $e) {
            self::assertContains('At least one scroller must be configured for "multiple" scroller.', $e->getMessage());
        }
        try {
            new MultipleScroller(['scrollers' => []]);
            self::fail('Invalid config must raise exception.');
        } catch (UserException $e) {
            self::assertContains('At least one scroller must be configured for "multiple" scroller.', $e->getMessage());
        }
        try {
            new MultipleScroller(['scrollers' => ['noScroller']]);
            self::fail('Invalid config must raise exception.');
        } catch (UserException $e) {
            self::assertContains('Scroller configuration for 0must be array.', $e->getMessage());
        }
        new MultipleScroller(['scrollers' => ['noScroller' => ['noScroller']]]);
    }

    public function testReset(): void
    {
        $scroller = new MultipleScroller($this->getScrollerConfig());
        $client = new RestClient(new NullLogger());
        $cursorConfig = new JobConfig([
            'endpoint' => 'arrData',
            'scroller' => 'cursor',
        ]);
        $pageConfig = new JobConfig([
            'endpoint' => 'someData',
            'scroller' => 'page',
        ]);

        $scroller->getNextRequest($client, $pageConfig, [], ['foo', 'bar']);
        $scroller->getNextRequest($client, $cursorConfig, [], [['id' => 2], ['id' => 1]]);
        $scroller->getNextRequest($client, $pageConfig, [], ['foo', 'bar']);
        $scroller->getNextRequest($client, $cursorConfig, [], [['id' => 3], ['id' => 4]]);
        $scroller->reset();

        $nextCursor = $scroller->getNextRequest($client, $cursorConfig, [], [['id' => 1], ['id' => 2]]);
        $expectedCursor = $client->createRequest([
            'endpoint' => 'arrData',
            'params' => [
                'newerThan' => 2,
            ],
        ]);
        self::assertEquals($expectedCursor, $nextCursor);

        $nextPage = $scroller->getNextRequest($client, $pageConfig, [], ['foo', 'bar']);
        $expectedPage = $client->createRequest([
            'endpoint' => 'someData',
            'params' => [
                'page' => 2,
            ],
        ]);
        self::assertEquals($expectedPage, $nextPage);
    }

    public function testCloneSafety(): void
    {
        $scroller = new MultipleScroller($this->getScrollerConfig());
        $client = new RestClient(new NullLogger());
        $cursorConfig = new JobConfig([
            'endpoint' => 'arrData',
            'scroller' => 'cursor',
        ]);
        $pageConfig = new JobConfig([
            'endpoint' => 'someData',
            'scroller' => 'page',
        ]);

        $scroller->getNextRequest($client, $pageConfig, [], ['foo', 'bar']);
        $scroller->getNextRequest($client, $cursorConfig, [], [['id' => 2], ['id' => 1]]);
        $scroller->getNextRequest($client, $pageConfig, [], ['foo', 'bar']);
        $nextCursor = $scroller->getNextRequest($client, $cursorConfig, [], [['id' => 3], ['id' => 4]]);
        $expectedCursor = $client->createRequest([
            'endpoint' => 'arrData',
            'params' => [
                'newerThan' => 4,
            ],
        ]);
        self::assertEquals($expectedCursor, $nextCursor);
        $nextPage = $scroller->getNextRequest($client, $pageConfig, [], ['foo', 'bar']);
        $expectedPage = $client->createRequest([
            'endpoint' => 'someData',
            'params' => [
                'page' => 4,
            ],
        ]);
        self::assertEquals($expectedPage, $nextPage);

        $scrollerClone = clone $scroller;
        $scrollerClone->reset();

        $nextCursor = $scroller->getNextRequest($client, $cursorConfig, [], [['id' => 3], ['id' => 4]]);
        $expectedCursor = $client->createRequest([
            'endpoint' => 'arrData',
            'params' => [
                'newerThan' => 4,
            ],
        ]);
        self::assertEquals($expectedCursor, $nextCursor);
        $nextPage = $scroller->getNextRequest($client, $pageConfig, [], ['foo', 'bar']);
        $expectedPage = $client->createRequest([
            'endpoint' => 'someData',
            'params' => [
                'page' => 5,
            ],
        ]);
        self::assertEquals($expectedPage, $nextPage);
    }

    public function testScrollers(): void
    {
        $scroller = new MultipleScroller($this->getScrollerConfig());
        self::assertCount(3, $scroller->getScrollers());
    }
}
