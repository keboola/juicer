<?php

namespace Keboola\Juicer\Tests\Pagination;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\MultipleScroller;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MultipleScrollerTest extends TestCase
{
    public function testGetNextRequest()
    {
        $scroller = new MultipleScroller($this->getScrollerConfig());

        $cursorResponse = [
            (object)['id' => 2],
            (object)['id' => 1]
        ];

        $paramResponse = (object)[
            'next_page_id' => 'page2'
        ];

        $noScrollerResponse = (object)[
            'results' => [
                (object)['data' => 'val1'],
                (object)['data' => 'val2']
            ]
        ];

        $client = RestClient::create(new NullLogger());

        $paramConfig = new JobConfig('param', [
            'endpoint' => 'structuredData',
            'scroller' => 'param'
        ]);

        $cursorConfig = new JobConfig('cursor', [
            'endpoint' => 'arrData',
            'scroller' => 'cursor'
        ]);

        $noScrollerConfig = new JobConfig('none', [
            'endpoint' => 'data'
        ]);

        $nextParam = $scroller->getNextRequest($client, $paramConfig, $paramResponse, []);
        $expectedParam = $client->createRequest([
            'endpoint' => 'structuredData',
            'params' => [
                'page_id' => 'page2'
            ]
        ]);
        self::assertEquals($expectedParam, $nextParam);

        $nextCursor = $scroller->getNextRequest($client, $cursorConfig, $cursorResponse, $cursorResponse);
        $expectedCursor = $client->createRequest([
            'endpoint' => 'arrData',
            'params' => [
                'newerThan' => 2
            ]
        ]);
        self::assertEquals($expectedCursor, $nextCursor);

        $nextNone = $scroller->getNextRequest($client, $noScrollerConfig, $noScrollerResponse, $noScrollerResponse->results);
        self::assertFalse($nextNone);
    }

    public function testGetNextRequestDefault()
    {
        $config = $this->getScrollerConfig();
        $config['default'] = 'cursor';
        $scroller = new MultipleScroller($config);

        $paramConfig = new JobConfig('param', [
            'endpoint' => 'structuredData',
            'scroller' => 'param'
        ]);

        $noScrollerConfig = new JobConfig('none', [
            'endpoint' => 'data'
        ]);

        $cursorResponse = [
            (object)['id' => 2],
            (object)['id' => 1]
        ];

        $paramResponse = (object)[
            'next_page_id' => 'page2'
        ];

        $client = RestClient::create(new NullLogger());

        $nextParam = $scroller->getNextRequest($client, $paramConfig, $paramResponse, []);
        $expectedParam = $client->createRequest([
            'endpoint' => 'structuredData',
            'params' => [
                'page_id' => 'page2'
            ]
        ]);
        self::assertEquals($expectedParam, $nextParam);

        $nextCursor = $scroller->getNextRequest($client, $noScrollerConfig, $cursorResponse, $cursorResponse);
        $expectedCursor = $client->createRequest([
            'endpoint' => 'data',
            'params' => [
                'newerThan' => 2
            ]
        ]);
        self::assertEquals($expectedCursor, $nextCursor);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Default scroller 'def' does not exist
     */
    public function testGetNextRequestDefaultException()
    {
        $config = $this->getScrollerConfig();
        $config['default'] = 'def';
        $scroller = new MultipleScroller($config);

        $noScrollerConfig = new JobConfig('none', [
            'endpoint' => 'data'
        ]);

        $scroller->getFirstRequest(RestClient::create(new NullLogger()), $noScrollerConfig);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage Scroller 'nonExistentScroller' not set in API definitions. Scrollers defined: param, cursor, page
     */
    public function testUndefinedScrollerException()
    {
        $config = $this->getScrollerConfig();
        $scroller = new MultipleScroller($config);

        $noScrollerConfig = new JobConfig('none', [
            'endpoint' => 'data',
            'scroller' => 'nonExistentScroller'
        ]);

        $scroller->getFirstRequest(RestClient::create(new NullLogger()), $noScrollerConfig);
    }

    protected function getScrollerConfig()
    {
        $responseParamConfig = [
            'method' => 'response.param',
            'responseParam' => 'next_page_id',
            'queryParam' => 'page_id'
        ];

        $cursorConfig = [
            'method' => 'cursor',
            'idKey' => 'id',
            'param' => 'newerThan'
        ];

        $pageConfig = [
            'method' => 'pagenum'
        ];

        return [
            'scrollers' => [
                'param' => $responseParamConfig,
                'cursor' => $cursorConfig,
                'page' => $pageConfig
            ]
        ];
    }
}
