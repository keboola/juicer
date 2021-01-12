<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Psr\Log\NullLogger;

class HasMoreScrollerDecoratorTest extends ExtractorTestCase
{
    public function testGetNextRequestHasMore(): void
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = [
            'nextPageFlag' => [
                'field' => 'hasMore',
                'stopOn' => false,
            ],
        ];

        $scroller = new OffsetScroller(['limit' => 10]);

        $decorated = new HasMoreScrollerDecorator($scroller, $config);
        self::assertInstanceOf('Keboola\Juicer\Pagination\OffsetScroller', $decorated->getScroller());

        $next = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => true],
            array_fill(0, 10, ['k' => 'v'])
        );
        self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $next);

        $noNext = $decorated->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => false],
            array_fill(0, 10, ['k' => 'v'])
        );
        self::assertFalse($noNext);
    }

    public function testHasMore(): void
    {
        $scroller = new HasMoreScrollerDecorator(new NoScroller, [
            'nextPageFlag' => [
                'field' => 'finished',
                'stopOn' => true,
            ],
        ]);

        $yes = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertTrue($yes);
        $no = self::callMethod($scroller, 'hasMore', [(object) ['finished' => true]]);
        self::assertFalse($no);
    }

    public function testHasMoreNotSet(): void
    {
        $scroller = new HasMoreScrollerDecorator(new NoScroller, []);

        $null = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertNull($null);
    }

    public function testCloneScrollerDecorator(): void
    {
        $client = new RestClient(new NullLogger());
        $jobConfig = new JobConfig(['endpoint' => 'test']);

        $config = [
            'nextPageFlag' => [
                'field' => 'hasMore',
                'stopOn' => false,
            ],
        ];

        $scroller = new OffsetScroller(['limit' => 10]);

        $decorator = new HasMoreScrollerDecorator($scroller, $config);

        $decorator->getNextRequest(
            $client,
            $jobConfig,
            (object) ['hasMore' => true],
            array_fill(0, 10, ['k' => 'v'])
        );

        $cloneDecorator = clone $decorator;

        $decoratorState = $decorator->getScroller()->getState();
        $cloneDecoratorState = $cloneDecorator->getScroller()->getState();

        self::assertEquals(10, $decoratorState['pointer']);
        self::assertEquals(0, $cloneDecoratorState['pointer']);
    }
}
