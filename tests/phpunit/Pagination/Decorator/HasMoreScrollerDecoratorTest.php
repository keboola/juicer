<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

class HasMoreScrollerDecoratorTest extends ExtractorTestCase
{
    public function testGetNextRequestHasMore(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = [
            'nextPageFlag' => [
                'field' => 'hasMore',
                'stopOn' => false,
            ],
        ];

        $scroller = new OffsetScroller(['limit' => 10]);

        $testHandler = new TestHandler();
        $logger = new Logger('has-more-test-logger');
        $logger->setHandlers([$testHandler]);

        $decorated = new HasMoreScrollerDecorator($scroller, $config, $logger);
        self::assertInstanceOf('Keboola\Juicer\Pagination\OffsetScroller', $decorated->getScroller());

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => true],
            array_fill(0, 10, ['k' => 'v']),
        );
        self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $next);

        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => false],
            array_fill(0, 10, ['k' => 'v']),
        );
        self::assertNull($noNext);

        self::assertTrue($testHandler->hasInfoThatContains(
            sprintf('Stopping scrolling because \'hasMore\' is \'false\''),
        ));
    }

    public function testHasMore(): void
    {
        $testHandler = new TestHandler();
        $logger = new Logger('has-more-test-logger');
        $logger->setHandlers([$testHandler]);

        $scroller = new HasMoreScrollerDecorator(
            new NoScroller,
            [
            'nextPageFlag' => [
                'field' => 'finished',
                'stopOn' => true,
            ],
            ],
            $logger,
        );

        $yes = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertTrue($yes);
        $no = self::callMethod($scroller, 'hasMore', [(object) ['finished' => true]]);
        self::assertFalse($no);

        self::assertTrue($testHandler->hasInfoThatContains(
            sprintf('Stopping scrolling because \'finished\' is \'true\''),
        ));
    }

    public function testHasMoreNotSet(): void
    {
        $scroller = new HasMoreScrollerDecorator(new NoScroller, [], new NullLogger());

        $null = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertNull($null);
    }

    public function testCloneScrollerDecorator(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = [
            'nextPageFlag' => [
                'field' => 'hasMore',
                'stopOn' => false,
            ],
        ];

        $scroller = new OffsetScroller(['limit' => 10]);

        $decorator = new HasMoreScrollerDecorator($scroller, $config, new NullLogger());

        $decorator->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => true],
            array_fill(0, 10, ['k' => 'v']),
        );

        $cloneDecorator = clone $decorator;

        $decoratorState = $decorator->getScroller()->getState();
        $cloneDecoratorState = $cloneDecorator->getScroller()->getState();

        self::assertEquals(10, $decoratorState['pointer']);
        self::assertEquals(0, $cloneDecoratorState['pointer']);
    }
}
