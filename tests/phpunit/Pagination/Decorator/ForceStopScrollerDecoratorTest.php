<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Pagination\Decorator;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\Decorator\ForceStopScrollerDecorator;
use Keboola\Juicer\Pagination\PageScroller;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ForceStopScrollerDecoratorTest extends TestCase
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

        $scroller = new PageScroller([]);

        $testHandler = new TestHandler();
        $logger = new Logger('forceStop-test-logger');
        $logger->setHandlers([$testHandler]);

        $decorator = new ForceStopScrollerDecorator($scroller, ['forceStop' => $config], $logger);

        $i = 0;
        while ($request = $decorator->getNextRequest($client, $jobConfig, $response, $response)) {
            self::assertInstanceOf('Keboola\Juicer\Client\RestRequest', $request);
            $i++;
        }
        self::assertNull($decorator->getNextRequest($client, $jobConfig, $response, $response));
        // Assert 3 pages were true
        self::assertEquals(3, $i);

        if (isset($config['pages'])) {
            self::assertTrue($testHandler->hasInfoThatContains(
                sprintf('Force stopping: page limit reached (%d pages).', $config['pages']),
            ));
        } elseif (isset($config['volume'])) {
            self::assertTrue($testHandler->hasInfoThatContains(
                sprintf('Force stopping: volume limit reached (%d bytes).', $config['volume']),
            ));
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

        $scroller = new PageScroller([]);

        $testHandler = new TestHandler();
        $logger = new Logger('forceStop-test-logger');
        $logger->setHandlers([$testHandler]);

        $decorator = new ForceStopScrollerDecorator($scroller, ['forceStop' => ['time' => 3]], $logger);
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

        self::assertTrue($testHandler->hasInfoThatContains(
            sprintf('Force stopping: time limit reached (%d seconds).', 3),
        ));
    }

    public function testCloneScrollerDecorator(): void
    {
        $client = RestClientMockBuilder::create()->getRestClient();
        $jobConfig = new JobConfig([
            'endpoint' => 'test',
        ]);

        $scroller = new PageScroller([]);

        $decorator = new ForceStopScrollerDecorator($scroller, ['forceStop' => ['time' => 3]], new NullLogger());

        $response = ['a'];
        $decorator->getNextRequest($client, $jobConfig, [$response], $response);

        $cloneDecorator = clone $decorator;

        $decoratorState = $decorator->getScroller()->getState();
        $cloneDecoratorState = $cloneDecorator->getScroller()->getState();

        self::assertEquals(2, $decoratorState['page']);
        self::assertEquals(1, $cloneDecoratorState['page']);
    }
}
