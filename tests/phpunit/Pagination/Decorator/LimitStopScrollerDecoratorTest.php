<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\Decorator\LimitStopScrollerDecorator;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use stdClass;

class LimitStopScrollerDecoratorTest extends ExtractorTestCase
{
    public function testField(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['field' => 'results.totalNumber']];

        $scroller = new PageScroller(['pageParam' => 'pageNo'], $this->logger);

        $decorated = new LimitStopScrollerDecorator($scroller, $config, $this->logger);
        $response = new stdClass();
        $response->results = (object) ['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data,
        );
        self::assertInstanceOf(RestRequest::class, $next);
        self::assertInstanceOf(PageScroller::class, $decorated->getScroller());

        $response->results = (object) ['totalNumber' => 15, 'pageNumber' => 2];
        $response->results->data = array_fill(0, 5, (object) ['key' => 'value2']);
        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data,
        );
        self::assertNull($noNext);

        self::assertLoggerContains(
            sprintf('Limit reached, stopping scrolling. Current count: 15, limit: 15'),
            'info',
        );
    }

    public function testLimit(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['count' => 12]];

        $scroller = new PageScroller(['pageParam' => 'pageNo'], $this->logger);

        $decorated = new LimitStopScrollerDecorator($scroller, $config, $this->logger);
        $response = new stdClass();
        $response->results = (object) ['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object) ['key' => 'value']);

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data,
        );
        self::assertInstanceOf(RestRequest::class, $next);
        self::assertInstanceOf(PageScroller::class, $decorated->getScroller());

        $response->results = (object) ['totalNumber' => 15, 'pageNumber' => 2];
        $response->results->data = array_fill(0, 5, (object) ['key' => 'value2']);
        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data,
        );
        self::assertNull($noNext);

        self::assertLoggerContains(
            'Limit reached, stopping scrolling. Current count: 15, limit: 12',
            'info',
        );
    }

    public function testInvalid1(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("One of 'limitStop.field' or 'limitStop.count' attributes is required.");
        new LimitStopScrollerDecorator(new NoScroller(), ['limitStop' => ['count' => 0]], $this->logger);
    }

    public function testInvalid2(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("Specify only one of 'limitStop.field' or 'limitStop.count'");
        new LimitStopScrollerDecorator(
            new NoScroller(),
            ['limitStop' => ['count' => 12, 'field' => 'whatever']],
            $this->logger,
        );
    }

    public function testCloneScrollerDecorator(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['count' => 12]];

        $scroller = new PageScroller(['pageParam' => 'pageNo'], $this->logger);
        $decorator = new LimitStopScrollerDecorator($scroller, $config, $this->logger);
        $response = new stdClass();
        $response->results = (object) ['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object) ['key' => 'value']);

        $decorator->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data,
        );

        $cloneDecorator = clone $decorator;

        $decoratorState = $decorator->getScroller()->getState();
        $cloneDecoratorState = $cloneDecorator->getScroller()->getState();

        self::assertEquals(2, $decoratorState['page']);
        self::assertEquals(1, $cloneDecoratorState['page']);
    }
}
