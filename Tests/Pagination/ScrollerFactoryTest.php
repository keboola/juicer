<?php

use Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\ScrollerFactory;

class ScrollerFactoryTest extends ExtractorTestCase
{
    public function testGetScroller()
    {
        self::assertInstanceOf('\Keboola\Juicer\Pagination\NoScroller', ScrollerFactory::getScroller([]));
        self::assertInstanceOf('\Keboola\Juicer\Pagination\CursorScroller', ScrollerFactory::getScroller([
            'method' => 'cursor',
            'idKey' => 'id',
            'param' => 'from'
        ]));
        self::assertInstanceOf('\Keboola\Juicer\Pagination\OffsetScroller', ScrollerFactory::getScroller([
            'method' => 'offset',
            'limit' => 2
        ]));
        self::assertInstanceOf('\Keboola\Juicer\Pagination\PageScroller', ScrollerFactory::getScroller([
            'method' => 'pagenum'
        ]));
        self::assertInstanceOf('\Keboola\Juicer\Pagination\ResponseUrlScroller', ScrollerFactory::getScroller([
            'method' => 'response.url'
        ]));
        self::assertInstanceOf('\Keboola\Juicer\Pagination\ResponseParamScroller', ScrollerFactory::getScroller([
            'method' => 'response.param',
            'responseParam' => 'scrollId',
            'queryParam' => 'scrollID'
        ]));
        self::assertInstanceOf('\Keboola\Juicer\Pagination\MultipleScroller', ScrollerFactory::getScroller([
            'method' => 'multiple',
            'scrollers' => ['none' => []]
        ]));

    }
}