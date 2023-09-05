<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Juicer\Tests\RestClientMockBuilder;

class ForceStopScrollerDecoratorTest extends ExtractorTestCase
{
    /**
     * @dataProvider limitProvider
     * @param array $config
     * @param array $response
     */
    public function testCheckLimits(array $config, array $response): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new PageScroller([], $this->logger);
        $decorator = new ForceStopScrollerDecorator($scroller, ['forceStop' => $config], $this->logger);

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, $response, $response)) {
            self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $request);
            $i++;
        }
        self::assertNull($decorator->getNextRequest($client, $jobConfig, $response, $response));
        // Assert 3 pages were true
        self::assertEquals(3, $i);

        if (isset($config['pages'])) {
            self::assertLoggerContains(
                sprintf('Force stopping: page limit reached (%d pages).', $config['pages']),
                'info',
            );
        } elseif (isset($config['volume'])) {
            self::assertLoggerContains(
                sprintf('Force stopping: volume limit reached (%d bytes).', $config['volume']),
                'info',
            );
        } else {
            $this->fail('No limit was reached');
        }
    }

    public function limitProvider(): array
    {
        $response = [
            (object) [
                'asdf' => 1234,
            ],
        ];

        return [
            'pages' => [
                ['pages' => 3],
                $response,
            ],
            'volume' => [
                ['volume' => strlen((string) json_encode($response)) * 3],
                $response,
            ],
        ];
    }

    public function testTimeLimit(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new PageScroller([], $this->logger);

        $decorator = new ForceStopScrollerDecorator($scroller, ['forceStop' => ['time' => 3]], $this->logger);
        $response = ['a'];

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, [$response], $response)) {
            self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $request);
            $i++;
            sleep(1);
        }
        self::assertNull($decorator->getNextRequest($client, $jobConfig, $response, $response));
        // Assert 3 pages were true
        self::assertEquals(3, $i);

        self::assertLoggerContains(
            sprintf('Force stopping: time limit reached (%d seconds).', 3),
            'info',
        );
    }

    public function testCloneScrollerDecorator(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new PageScroller([], $this->logger);

        $decorator = new ForceStopScrollerDecorator($scroller, ['forceStop' => ['time' => 3]], $this->logger);

        $response = ['a'];
        $decorator->getNextRequest($client, $jobConfig, [$response], $response);

        $cloneDecorator = clone $decorator;

        $decoratorState = $decorator->getScroller()->getState();
        $cloneDecoratorState = $cloneDecorator->getScroller()->getState();

        self::assertEquals(2, $decoratorState['page']);
        self::assertEquals(1, $cloneDecoratorState['page']);
    }
}
