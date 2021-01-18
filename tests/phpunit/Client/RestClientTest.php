<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Client;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

class RestClientTest extends ExtractorTestCase
{
    private array $history = [];

    public function testCreateRequest(): void
    {
        $arr = [
            'first' => 1,
            'second' => 'two',
        ];
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'params' => $arr,
        ]);

        $client = new RestClient(new NullLogger(), [], []);
        $request = $client->createRequest($jobConfig->getConfig());
        $expected = new RestRequest(['endpoint' => 'ep', 'params' => $arr]);
        self::assertEquals($expected, $request);
    }

    public function testDownload(): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';
        $restClient = $this->createMockClient(
            [
                new Response(200, [], Utils::streamFor($body)),
            ],
            [
                'headers' => ['X-Test' => '1234'],
            ]
        );

        $request = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1]]);
        $result = $restClient->download($request);

        /** @var RequestInterface $lastRequest */
        $lastRequest = array_pop($this->history)['request'];
        self::assertEquals(json_decode($body), $result);
        self::assertEquals('ep?a=1', (string) $lastRequest->getUri());
        self::assertEquals('GET', $lastRequest->getMethod());
        self::assertEquals([1234], $lastRequest->getHeaders()['X-Test']);
    }

    public function testRequestHeaders(): void
    {
        $body = '{}';
        $restClient = $this->createMockClient(
            [
                new Response(200, [], Utils::streamFor($body)),
            ],
            [
                'headers' => ['X-Test' => '1234'],
            ]
        );

        $request = new RestRequest([
            'endpoint' => 'ep',
            'params' => [],
            'method' => 'GET',
            'headers' => ['X-RTest' => 'requestHeader'],
        ]);
        $result = $restClient->download($request);

        /** @var RequestInterface $lastRequest */
        $lastRequest = array_pop($this->history)['request'];
        $headers = $lastRequest->getHeaders();
        self::assertEquals((object) [], $result);
        self::assertSame(['requestHeader'], $headers['X-RTest']);
        self::assertSame(['1234'], $headers['X-Test']);
    }


    public function testRetryHeaderRelative(): void
    {
        $delaySec = 5;
        $retryOptions = [];
        $errorResponse = new Response(429, ['Retry-After' => $delaySec]);
        $this->runAndAssertDelay($retryOptions, $errorResponse, $delaySec);
    }

    public function testRetryHeaderAbsolute(): void
    {
        $delaySec = 5;
        $retryOptions = [];
        $errorResponse = new Response(429, ['Retry-After' => time() + $delaySec]);
        $this->runAndAssertDelay($retryOptions, $errorResponse, $delaySec);
    }

    public function testRetryHeaderDate(): void
    {
        $delaySec = 5;
        $date = new \DateTimeImmutable("+ ${delaySec} seconds");
        $retryOptions = [];
        $errorResponse = new Response(429, ['Retry-After' => $date->format(DATE_RFC1123)]);
        $this->runAndAssertDelay($retryOptions, $errorResponse, $delaySec);
    }


    public function testRetryHeaderCustomName(): void
    {
        $delaySec = 5;
        $retryOptions = [
            'http' => [
                'retryHeader' => 'X-Rate-Limit-Reset',
                'codes' => [403, 429],
            ],
            'maxRetries' => 8,
        ];
        $errorResponse = new Response(403, ['X-Rate-Limit-Reset' => $delaySec]);
        $this->runAndAssertDelay($retryOptions, $errorResponse, $delaySec);
    }

    public function testCurlBackoffMappedError(): void
    {
        // mapped curl error
        $retries = 3;
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);

        $client = new RestClient(
            $logger,
            [],
            [
                'maxRetries' => $retries,
                'curl' => [
                    'codes' => [6],
                ],
            ]
        );

        try {
            $client->download(new RestRequest(['endpoint' => 'http://keboolakeboolakeboola.com']));
            self::fail('Request should fail');
        } catch (\Throwable $e) {
            self::assertCount($retries, $handler->getRecords());

            $delays = [1, 2, 4]; // exponential
            foreach ($handler->getRecords() as $i => $record) {
                self::assertSame(Logger::DEBUG, $record['level']);
                self::assertSame(
                    sprintf('Http request failed, retrying in %.1f seconds [%dx].', $delays[$i], $i + 1),
                    $record['message']
                );
                self::assertMatchesRegularExpression('/curl error 6\:/ui', $record['context']['message']);
            }

            self::assertMatchesRegularExpression('/curl error 6\:/ui', $e->getMessage());
            self::assertTrue($e instanceof UserException);
        }
    }

    public function testCurlBackoffNotMappedError(): void
    {
        $retries = 3;
        $handler = new TestHandler();
        $logger = new Logger('test', [
            $handler,
        ]);

        $client = new RestClient(
            $logger,
            [],
            [
                'maxRetries' => $retries,
                'curl' => [
                    'codes' => [77],
                ],
            ]
        );

        try {
            $client->download(new RestRequest(['endpoint' => 'http://keboolakeboolakeboola.com']));
            self::fail('Request should fail');
        } catch (\Throwable $e) {
            self::assertCount(0, $handler->getRecords());
            self::assertMatchesRegularExpression('/curl error 6\:/ui', $e->getMessage());
            self::assertTrue($e instanceof UserException);
        }
    }

    public function testErrorCodesIgnoreNoIgnore(): void
    {
        $restClient = $this->createMockClient(
            [
                new Response(404, [], Utils::streamFor('{"a": "b"}')),
            ]
        );
        try {
            $restClient->download(new RestRequest(['endpoint' => 'ep']));
            self::fail('Request should fail');
        } catch (\Throwable $e) {
            self::assertStringContainsString('Not Found', $e->getMessage());
            self::assertStringContainsString('404', $e->getMessage());
        }
    }

    public function testErrorCodesIgnore(): void
    {
        $responses = [new Response(404, [], Utils::streamFor('{"a": "b"}'))];
        $ignoredErrors = [404];
        $restClient = $this->createMockClient($responses, [], [], [], $ignoredErrors);
        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals((object) ['a' => 'b'], $response);
    }

    public function testErrorCodesIgnoreServer(): void
    {
        $responses = [
            new Response(503, [], Utils::streamFor('{"a": "b"}')),
            new Response(503, [], Utils::streamFor('{"a": "b"}')),
            new Response(503, [], Utils::streamFor('{"a": "b"}')),
            new Response(503, [], Utils::streamFor('{"a": "b"}')),
        ];
        $ignoredErrors = [503];
        $restClient = $this->createMockClient($responses, [], ['maxRetries' => 3], [], $ignoredErrors);
        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['a' => 'b'], (array) $response);
    }

    public function testErrorCodesIgnoreInvalidResponse(): void
    {
        $responses = [new Response(200, [], Utils::streamFor('{"a": bcd"'))];
        $ignoredErrors = [200];
        $restClient = $this->createMockClient($responses, [], [], [], $ignoredErrors);
        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['errorData' => '{"a": bcd"'], (array) $response);
    }

    public function testErrorCodesIgnoreInvalidResponseAndCode(): void
    {
        $responses = [new Response(404, [], Utils::streamFor('{"a": bcd"'))];
        $ignoredErrors = [404];
        $restClient = $this->createMockClient($responses, [], [], [], $ignoredErrors);
        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals((object) ['errorData' => '{"a": bcd"'], $response);
    }

    public function testErrorCodesIgnoreEmptyResponse(): void
    {
        $responses = [new Response(404, [], null)];
        $ignoredErrors = [404];
        $restClient = $this->createMockClient($responses, [], [], [], $ignoredErrors);
        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals((object) ['errorData' => ''], $response);
    }

    public function testMalformedJson(): void
    {
        $body = '[
                {"field": "d
        ]';
        $responses = [new Response(200, [], Utils::streamFor($body))];
        $restClient = $this->createMockClient($responses);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Invalid JSON response from API: JSON decode error:');
        try {
            $restClient->download(new RestRequest(['endpoint' => 'ep']));
        } catch (UserException $e) {
            self::assertArrayHasKey('errDetail', $e->getData());
            self::assertArrayHasKey('json', $e->getData());
            throw $e;
        }
    }

    public function testDefaultRequestOptions(): void
    {
        $defaultOptions = [
            'method' => 'POST',
            'params' => [
                'defA' => 'defValA',
                'defB' => 'defValB',
            ],
        ];

        $client = new RestClient(new NullLogger(), [], [], $defaultOptions);
        $requestOptions = [
            'endpoint' => 'ep',
            'params' => [
                'defB' => 'overrideB',
            ],
        ];
        $request = $client->createRequest($requestOptions);

        self::assertEquals($defaultOptions['method'], $request->getMethod());
        self::assertEquals($requestOptions['endpoint'], $request->getEndpoint());
        self::assertEquals(
            array_replace($defaultOptions['params'], $requestOptions['params']),
            $request->getParams()
        );
    }

    protected function runAndAssertDelay(array $retryOptions, Response $errResponse, int $expectedDelaySec): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';
        $restClient = $this->createMockClient(
            [
                $errResponse,
                new Response(200, [], Utils::streamFor($body)),
            ],
            [
                'headers' => ['X-Test' => '1234'],
            ],
            $retryOptions
        );

        $request = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1]]);

        // Run download
        $startTime = microtime(true);
        $result = $restClient->download($request);
        $endTime = microtime(true);
        $measuredDelaySec = $endTime - $startTime;

        /** @var array $lastOptions */
        $lastOptions = array_pop($this->history)['options'];
        self::assertEquals(json_decode($body), $result);
        self::assertEquals($expectedDelaySec * 1000, $lastOptions['delay']);
        self::assertGreaterThanOrEqual($expectedDelaySec, $measuredDelaySec);
    }

    private function createMockClient(
        array $queue,
        array $options = [],
        array $retryOptions = [],
        array $defaultOptions = [],
        array $ignoreErrors = []
    ): RestClient {
        $handler = HandlerStack::create(new MockHandler($queue));
        $options['handler'] = $handler;
        $restClient = new RestClient(new NullLogger(), $options, $retryOptions, $defaultOptions, $ignoreErrors);

        // To log retries, history middleware must be pushed after retry middleware in RestClient.
        $handler->push(Middleware::history($this->history));

        return $restClient;
    }
}
