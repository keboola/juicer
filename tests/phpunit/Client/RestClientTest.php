<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Client;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

class RestClientTest extends ExtractorTestCase
{
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

        $client = RestClientMockBuilder::create()->getRestClient();
        $request = $client->createRequest($jobConfig->getConfig());
        $expected = new RestRequest(['endpoint' => 'ep', 'params' => $arr]);
        self::assertEquals($expected, $request);
    }

    public function testCreateRequestWithDefaults(): void
    {
        $arr = [
            'first' => 1,
            'second' => 'two',
        ];
        $jobConfig = new JobConfig(['endpoint' => 'ep', 'params' => $arr]);
        $client = RestClientMockBuilder::create()
            ->setRequestDefaultOptions(['params' => ['default' => 'param']])
            ->getRestClient();

        $request = $client->createRequest($jobConfig->getConfig());
        $expected = new RestRequest(['endpoint' => 'ep', 'params' => [
            'first' => 1,
            'second' => 'two',
            'default' => 'param',
        ]]);
        self::assertEquals($expected, $request);
    }

    public function testCreateRequestWithoutDefaults(): void
    {
        $arr = [
            'first' => 1,
            'second' => 'two',
        ];
        $jobConfig = new JobConfig(['endpoint' => 'ep', 'params' => $arr]);
        $client = RestClientMockBuilder::create()
            ->setRequestDefaultOptions(['params' => ['default' => 'param']])
            ->getRestClient();

        $request = $client->createRequest($jobConfig->getConfig(), false);
        $expected = new RestRequest(['endpoint' => 'ep', 'params' => [
            'first' => 1,
            'second' => 'two',
        ]]);
        self::assertEquals($expected, $request);
    }

    public function testDownload(): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200($body)
            ->setBaseUri('http://example.com')
            ->setGuzzleConfig(['headers' => ['X-Test' => '1234']])
            ->setHistoryContainer($history)
            ->getRestClient();

        $request = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1]]);
        $result = $restClient->download($request);

        $lastRequest = $history->last()->getRequest();
        self::assertEquals(json_decode($body), $result);
        self::assertEquals('http://example.com/ep?a=1', (string) $lastRequest->getUri());
        self::assertEquals('GET', $lastRequest->getMethod());
        self::assertEquals([1234], $lastRequest->getHeaders()['X-Test']);
    }

    public function testRequestHeaders(): void
    {
        $body = '{}';
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200($body)
            ->setGuzzleConfig(['headers' => ['X-Test' => '1234']])
            ->setHistoryContainer($history)
            ->getRestClient();

        $request = new RestRequest([
            'endpoint' => 'ep',
            'params' => [],
            'method' => 'GET',
            'headers' => ['X-RTest' => 'requestHeader'],
        ]);
        $result = $restClient->download($request);

        $lastRequest = $history->last()->getRequest();
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
            'http://keboolakeboolakeboola.com',
            [],
            [
                'maxRetries' => $retries,
                'curl' => [
                    'codes' => [6],
                ],
            ]
        );

        try {
            $client->download(new RestRequest(['endpoint' => '/']));
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
            'http://keboolakeboolakeboola.com',
            [],
            [
                'maxRetries' => $retries,
                'curl' => [
                    'codes' => [77],
                ],
            ]
        );

        try {
            $client->download(new RestRequest(['endpoint' => '/']));
            self::fail('Request should fail');
        } catch (\Throwable $e) {
            self::assertCount(0, $handler->getRecords());
            self::assertMatchesRegularExpression('/curl error 6\:/ui', $e->getMessage());
            self::assertTrue($e instanceof UserException);
        }
    }

    public function testErrorCodesIgnoreNoIgnore(): void
    {
        $restClient = RestClientMockBuilder::create()
            ->addResponse404()
            ->getRestClient();

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
        $restClient = RestClientMockBuilder::create()
            ->addResponse404('{"a": "b"}')
            ->addIgnoredError(404)
            ->getRestClient();

        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals((object) ['a' => 'b'], $response);
    }

    public function testErrorCodesIgnoreServer(): void
    {
        $restClient = RestClientMockBuilder::create()
            ->addResponseRepeatedly(4, new Response(503, [], Utils::streamFor('{"a": "b"}')))
            ->addIgnoredError(503)
            ->setRetryConfig(['maxRetries' => 3])
            ->getRestClient();

        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['a' => 'b'], (array) $response);
    }

    public function testErrorCodesIgnoreInvalidResponse(): void
    {
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('{"a": bcd"')
            ->addIgnoredError(200)
            ->getRestClient();

        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['errorData' => '{"a": bcd"'], (array) $response);
    }

    public function testErrorCodesIgnoreInvalidResponseAndCode(): void
    {
        $restClient = RestClientMockBuilder::create()
            ->addResponse404('{"a": bcd"')
            ->addIgnoredError(404)
            ->getRestClient();

        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals((object) ['errorData' => '{"a": bcd"'], $response);
    }

    public function testErrorCodesIgnoreEmptyResponse(): void
    {
        $restClient = RestClientMockBuilder::create()
            ->addResponse404(null)
            ->addIgnoredError(404)
            ->getRestClient();

        $response = $restClient->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals((object) ['errorData' => ''], $response);
    }

    public function testMalformedJson(): void
    {
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('[ {"field": "d ]')
            ->getRestClient();

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

        $client = RestClientMockBuilder::create()
            ->setRequestDefaultOptions($defaultOptions)
            ->getRestClient();

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

    public function testHostHeaderInRequest(): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200($body)
            ->setBaseUri('http://example.com')
            ->setGuzzleConfig([
                'headers' => [
                    'X-Test' => '1234',
                    'Host' => 'default.com',
                ],
            ])
            ->setHistoryContainer($history)
            ->getRestClient();

        $request = new RestRequest([
            'endpoint' => 'ep',
            'headers' => [
                'Host' => 'different.com',
            ],
        ]);

        $restClient->download($request);
        $lastRequest = $history->last()->getRequest();
        self::assertEquals('different.com', $lastRequest->getHeaders()['Host'][0]);
    }

    public function testHostHeaderInConfig(): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200($body)
            ->setBaseUri('http://example.com')
            ->setGuzzleConfig([
                'headers' => [
                    'X-Test' => '1234',
                    'Host' => 'different.com',
                ],
            ])
            ->setHistoryContainer($history)
            ->getRestClient();

        $request = new RestRequest([
            'endpoint' => 'ep',
        ]);

        $restClient->download($request);
        $lastRequest = $history->last()->getRequest();
        self::assertEquals('different.com', $lastRequest->getHeaders()['Host'][0]);
    }

    protected function runAndAssertDelay(array $retryConfig, Response $errResponse, int $expectedDelaySec): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse($errResponse)
            ->addResponse200($body)
            ->setGuzzleConfig(['headers' => ['X-Test' => '1234']])
            ->setRetryConfig($retryConfig)
            ->setHistoryContainer($history)
            ->getRestClient();

        $request = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1]]);

        // Run download
        $startTime = microtime(true);
        $result = $restClient->download($request);
        $endTime = microtime(true);
        $measuredDelaySec = $endTime - $startTime;

        $lastOptions = $history->last()->getOptions();
        self::assertEquals(json_decode($body), $result);
        self::assertEquals($expectedDelaySec * 1000, $lastOptions['delay']);
        self::assertGreaterThanOrEqual($expectedDelaySec, $measuredDelaySec);
    }
}
