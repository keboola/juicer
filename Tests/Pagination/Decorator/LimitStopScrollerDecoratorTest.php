<?php

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\Decorator\LimitStopScrollerDecorator;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Psr\Log\NullLogger;

class LimitStopScrollerDecoratorTest extends ExtractorTestCase
{
    public function testField()
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['field' => 'results.totalNumber']];

        $scroller = new PageScroller(['pageParam' => 'pageNo']);
        $decorated = new LimitStopScrollerDecorator($scroller, $config);
        $response = new \stdClass();
        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object)['key' => 'value']);

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertInstanceOf(RestRequest::class, $next);
        self::assertInstanceOf(PageScroller::class, $decorated->getScroller());

        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 2];
        $response->results->data = array_fill(0, 5, (object)['key' => 'value2']);
        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertFalse($noNext);
    }

    public function testLimit()
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['count' => 12]];

        $scroller = new PageScroller(['pageParam' => 'pageNo']);
        $decorated = new LimitStopScrollerDecorator($scroller, $config);
        $response = new \stdClass();
        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object)['key' => 'value']);

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertInstanceOf(RestRequest::class, $next);
        self::assertInstanceOf(PageScroller::class, $decorated->getScroller());

        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 2];
        $response->results->data = array_fill(0, 5, (object)['key' => 'value2']);
        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );
        self::assertFalse($noNext);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage One of 'limitStop.field' or 'limitStop.count' attributes is required.
     */
    public function testInvalid1()
    {
        new LimitStopScrollerDecorator(new NoScroller(), ['limitStop' => ['count' => 0]]);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Specify only one of 'limitStop.field' or 'limitStop.count'
     */
    public function testInvalid2()
    {
        new LimitStopScrollerDecorator(new NoScroller(), ['limitStop' => ['count' => 12, 'field' => 'whatever']]);
    }

    public function testCloneScrollerDecorator()
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = ['limitStop' => ['count' => 12]];

        $scroller = new PageScroller(['pageParam' => 'pageNo']);
        $decorator = new LimitStopScrollerDecorator($scroller, $config);
        $response = new \stdClass();
        $response->results = (object)['totalNumber' => 15, 'pageNumber' => 1];
        $response->results->data = array_fill(0, 10, (object)['key' => 'value']);

        $decorator->getNextRequest(
            $client,
            $jobConfig,
            $response,
            $response->results->data
        );

        $cloneDecorator = clone $decorator;

        $decoratorState = $decorator->getScroller()->getState();
        $cloneDecoratorState = $cloneDecorator->getScroller()->getState();

        self::assertEquals(2, $decoratorState['page']);
        self::assertEquals(1, $cloneDecoratorState['page']);
    }
}
