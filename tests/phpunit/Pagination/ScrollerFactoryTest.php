<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\CursorScroller;
use Keboola\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;
use Keboola\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Keboola\Juicer\Pagination\Decorator\LimitStopScrollerDecorator;
use Keboola\Juicer\Pagination\MultipleScroller;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Pagination\ResponseParamScroller;
use Keboola\Juicer\Pagination\ResponseUrlScroller;
use Keboola\Juicer\Pagination\ScrollerFactory;
use Keboola\Juicer\Pagination\ZendeskResponseUrlScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use PHPUnit\Framework\TestCase;

class ScrollerFactoryTest extends ExtractorTestCase
{
    public function testCreateScroller(): void
    {
        self::assertInstanceOf(NoScroller::class, ScrollerFactory::getScroller([]));
        self::assertInstanceOf(CursorScroller::class, ScrollerFactory::getScroller([
            'method' => 'cursor',
            'idKey' => 'id',
            'param' => 'from',
        ], $this->logger));
        self::assertInstanceOf(OffsetScroller::class, ScrollerFactory::getScroller([
            'method' => 'offset',
            'limit' => 2,
        ], $this->logger));
        self::assertInstanceOf(PageScroller::class, ScrollerFactory::getScroller([
            'method' => 'pagenum',
        ], $this->logger));
        self::assertInstanceOf(ResponseUrlScroller::class, ScrollerFactory::getScroller([
            'method' => 'response.url',
        ], $this->logger));
        self::assertInstanceOf(ResponseParamScroller::class, ScrollerFactory::getScroller([
            'method' => 'response.param',
            'responseParam' => 'scrollId',
            'queryParam' => 'scrollID',
        ], $this->logger));
        self::assertInstanceOf(MultipleScroller::class, ScrollerFactory::getScroller([
            'method' => 'multiple',
            'scrollers' => ['none' => []],
        ], $this->logger));
        self::assertInstanceOf(ZendeskResponseUrlScroller::class, ScrollerFactory::getScroller([
            'method' => 'zendesk.response.url',
        ], $this->logger));
    }

    public function testDecorateScroller(): void
    {
        self::assertInstanceOf(HasMoreScrollerDecorator::class, ScrollerFactory::getScroller([
            'nextPageFlag' => [
                'field' => 'continue',
                'stopOn' => false,
            ],
            'method' => 'pagenum',
        ], $this->logger));
        self::assertInstanceOf(ForceStopScrollerDecorator::class, ScrollerFactory::getScroller([
            'forceStop' => [
                'pages' => 2,
            ],
        ], $this->logger));
        self::assertInstanceOf(LimitStopScrollerDecorator::class, ScrollerFactory::getScroller([
            'limitStop' => [
                'count' => 10,
            ],
        ], $this->logger));
    }

    public function testInvalid(): void
    {
        try {
            ScrollerFactory::getScroller(['method' => 'fooBar'], $this->logger);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString('Unknown pagination method \'fooBar\'', $e->getMessage());
        }
        try {
            ScrollerFactory::getScroller(['method' => ['foo' => 'bar']], $this->logger);
            self::fail('Must raise exception');
        } catch (UserException $e) {
            self::assertStringContainsString('Unknown pagination method \'{"foo":"bar"}\'', $e->getMessage());
        }
    }
}
