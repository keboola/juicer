<?php

use Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\AbstractScroller;

class AbstractScrollerTest extends ExtractorTestCase
{
    public function testHasMore()
    {
        $scroller = $this->getMockForAbstractClass('Keboola\Juicer\Pagination\AbstractScroller', [[
            'nextPageFlag' => [
                'field' => 'finished',
                'stopOn' => true
            ]
        ]]);

        $yes = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertTrue($yes);
        $no = self::callMethod($scroller, 'hasMore', [(object) ['finished' => true]]);
        self::assertFalse($no);
    }

    public function testHasMoreNotSet()
    {
        $scroller = $this->getMockForAbstractClass('Keboola\Juicer\Pagination\AbstractScroller', [[]]);

        $null = self::callMethod($scroller, 'hasMore', [(object) ['finished' => false]]);
        self::assertNull($null);
    }
}
