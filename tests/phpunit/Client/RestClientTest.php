<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Client;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Hoa\Iterator\Mock;
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
        self::assertEquals('ep?a=1', (string)$lastRequest->getUri());
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
        self::assertEquals((object)[], $result);
        self::assertSame(['requestHeader'], $headers['X-RTest']);
        self::assertSame(['1234'], $headers['X-Test']);
    }

    /**
     * Cannot use dataProvider because that gets set up before all tests
     * and the delay causes issues
     */
    public function testStatusBackoff(): void
    {
        $sets = [
            'default' => [
                [],
                new Response(429, ['Retry-After' => 5]),
            ],
            'custom' => [
                [
                    'http' => [
                        'retryHeader' => 'X-Rate-Limit-Reset',
                        'codes' => [403, 429],
                    ],
                    'maxRetries' => 8,
                ],
                new Response(403, ['X-Rate-Limit-Reset' => 5]),
            ],
            'absolute' => [
                [],
                new Response(429, ['Retry-After' => time() + 5]),
            ],
        ];

        foreach ($sets as $set) {
            [$retryOptions, $errorResponse] = $set;
            $this->runBackoff($retryOptions, $errorResponse);
        }
    }

    /**
     * Cannot use dataProvider because that gets set up before all tests
     * and the delay causes issues
     */
    public function testCurlBackoff(): void
    {
        // mapped curl error
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
                    'codes' => [6],
                ],
            ]
        );

        try {
            $client->download(new RestRequest(['endpoint' => 'http://keboolakeboolakeboola.com']));
            self::fail('Request should fail');
        } catch (\Throwable $e) {
            self::assertCount($retries, $handler->getRecords());

            foreach ($handler->getRecords() as $record) {
                self::assertEquals(100, $record['level']);
                self::assertMatchesRegularExpression('/retrying/ui', $record['message']);
                self::assertMatchesRegularExpression('/curl error 6\:/ui', $record['context']['message']);
            }

            self::assertMatchesRegularExpression('/curl error 6\:/ui', $e->getMessage());
            self::assertTrue($e instanceof UserException);
        }

        // non-mapped curl error
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
        $responses = [];
        for ($i = 0; $i < 15; $i++) {
            $responses[] = new Response(503, [], Utils::streamFor('{"a": "b"}'));
        }
        $ignoredErrors = [503];
        $restClient = $this->createMockClient($responses, [], [], [], $ignoredErrors);
        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['a' => 'b'], (array)$response);
    }

    public function testErrorCodesIgnoreInvalidResponse(): void
    {
        $responses = [new Response(200, [], Utils::streamFor('{"a": bcd"'))];
        $ignoredErrors = [200];
        $restClient = $this->createMockClient($responses, [], [], [], $ignoredErrors);
        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['errorData' => '{"a": bcd"'], (array)$response);
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

    protected function runBackoff(array $retryOptions, Response $errResponse): void
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
        $result = $restClient->download($request);

        /** @var array $lastOptions */
        $lastOptions = array_pop($this->history)['options'];
        self::assertEquals(json_decode($body), $result);
        self::assertEquals(5000, $lastOptions['delay']);
    }

    private function createMockClient(
        array $queue,
        array $options = [],
        array $retryOptions = [],
        array $defaultOptions = [],
        array $ignoreErrors = []
    ): RestClient {
        $handler = HandlerStack::create(new MockHandler($queue));
        $handler->push(Middleware::history($this->history));
        $options['handler'] = $handler;
        return new RestClient(new NullLogger(), $options, $retryOptions, $defaultOptions, $ignoreErrors);
    }
}
