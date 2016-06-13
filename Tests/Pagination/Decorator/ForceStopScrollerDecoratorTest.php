<?php

use Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\PageScroller,
    Keboola\Juicer\Pagination\NoScroller,
    Keboola\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;

class ForceStopScrollerDecoratorTest extends ExtractorTestCase
{
    public function testCheckPages()
    {
        $client = RestClient::create();
        $jobConfig = new JobConfig('test', [
            'endpoint' => 'test'
        ]);

        $scroller = new PageScroller([]);

        $max = 3;

        $decorator = new ForceStopScrollerDecorator($scroller, [
            'forceStop' => [
                'pages' => $max
            ]
        ]);

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, [], ['a'])) {
            self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $request);
            $i++;
        }
        self::assertFalse($decorator->getNextRequest($client, $jobConfig, [], []));
        // Assert 3 pages were true
        self::assertEquals(3, $i);
    }
}

