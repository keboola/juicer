<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\CursorScroller;
use Keboola\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;
use Keboola\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Keboola\Juicer\Pagination\FacebookResponseUrlScroller;
use Keboola\Juicer\Pagination\MultipleScroller;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Pagination\ResponseParamScroller;
use Keboola\Juicer\Pagination\ResponseUrlScroller;
use Keboola\Juicer\Pagination\ScrollerFactory;
use Keboola\Juicer\Pagination\ZendeskResponseUrlScroller;
use PHPUnit\Framework\TestCase;

class ScrollerFactoryTest extends TestCase
{
    public function testCreateScroller()
    {
        self::assertInstanceOf(NoScroller::class, ScrollerFactory::getScroller([]));
        self::assertInstanceOf(CursorScroller::class, ScrollerFactory::getScroller([
            'method' => 'cursor',
            'idKey' => 'id',
            'param' => 'from'
        ]));
        self::assertInstanceOf(OffsetScroller::class, ScrollerFactory::getScroller([
            'method' => 'offset',
            'limit' => 2
        ]));
        self::assertInstanceOf(PageScroller::class, ScrollerFactory::getScroller([
            'method' => 'pagenum'
        ]));
        self::assertInstanceOf(ResponseUrlScroller::class, ScrollerFactory::getScroller([
            'method' => 'response.url'
        ]));
        self::assertInstanceOf(ResponseParamScroller::class, ScrollerFactory::getScroller([
            'method' => 'response.param',
            'responseParam' => 'scrollId',
            'queryParam' => 'scrollID'
        ]));
        self::assertInstanceOf(MultipleScroller::class, ScrollerFactory::getScroller([
            'method' => 'multiple',
            'scrollers' => ['none' => []]
        ]));
        self::assertInstanceOf(ZendeskResponseUrlScroller::class, ScrollerFactory::getScroller([
            'method' => 'zendesk.response.url'
        ]));
        self::assertInstanceOf(FacebookResponseUrlScroller::class, ScrollerFactory::getScroller([
            'method' => 'facebook.response.url'
        ]));
    }

    public function testDecorateScroller()
    {
        self::assertInstanceOf(HasMoreScrollerDecorator::class, ScrollerFactory::getScroller([
            'nextPageFlag' => [
                'field' => 'continue',
                'stopOn' => 'false'
            ],
            'method' => 'pagenum'
        ]));
        self::assertInstanceOf(ForceStopScrollerDecorator::class, ScrollerFactory::getScroller([
            'forceStop' => [
                'pages' => 2
            ]
        ]));
    }

    public function testInvalid()
    {
        try {
            ScrollerFactory::getScroller(['method' => 'fooBar']);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Unknown pagination method \'fooBar\'', $e->getMessage());
        }
        try {
            ScrollerFactory::getScroller(['method' => ['foo' => 'bar']]);
            self::fail("Must raise exception");
        } catch (UserException $e) {
            self::assertContains('Unknown pagination method \'{"foo":"bar"}\'', $e->getMessage());
        }
    }
}
