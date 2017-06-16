<?php

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;
use Psr\Log\NullLogger;

class ForceStopScrollerDecoratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider limitProvider
     * @param array $config
     * @param $response
     */
    public function testCheckLimits(array $config, $response)
    {
        $client = RestClient::create(new NullLogger());
        $jobConfig = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new PageScroller([]);

        $decorator = new ForceStopScrollerDecorator($scroller, [
            'forceStop' => $config
        ]);

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, $response, $response)) {
            self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $request);
            $i++;
        }
        self::assertFalse($decorator->getNextRequest($client, $jobConfig, $response, $response));
        // Assert 3 pages were true
        self::assertEquals(3, $i);
    }

    public function limitProvider()
    {
        $response = [
            (object)[
                'asdf' => 1234
            ]
        ];

        return [
            'pages' => [
                ['pages' => 3],
                $response
            ],
            'volume' => [
                ['volume' => strlen(json_encode($response)) * 3],
                $response
            ]
        ];
    }

    public function testTimeLimit()
    {
        $client = RestClient::create(new NullLogger());
        $jobConfig = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new PageScroller([]);

        $decorator = new ForceStopScrollerDecorator($scroller, [
            'forceStop' => [
                'time' => 3
            ]
        ]);

        $response = ['a'];

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, [$response], $response)) {
            self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $request);
            $i++;
            sleep(1);
        }
        self::assertFalse($decorator->getNextRequest($client, $jobConfig, $response, $response));
        // Assert 3 pages were true
        self::assertEquals(3, $i);
    }
}
