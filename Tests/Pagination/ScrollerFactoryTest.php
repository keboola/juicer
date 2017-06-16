<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Pagination\CursorScroller;
use Keboola\Juicer\Pagination\Decorator\HasMoreScrollerDecorator;
use Keboola\Juicer\Pagination\MultipleScroller;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\OffsetScroller;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Pagination\ResponseParamScroller;
use Keboola\Juicer\Pagination\ResponseUrlScroller;
use Keboola\Juicer\Pagination\ScrollerFactory;

class ScrollerFactoryTest extends \PHPUnit_Framework_TestCase
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
    }
}
