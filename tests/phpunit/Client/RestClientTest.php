<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Client;

use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Config\JobConfig;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Tests\ExtractorTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

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

        $client = new RestClient(new NullLogger(), [], []);
        $request = $client->createRequest($jobConfig->getConfig());

        $expected = new RestRequest(['endpoint' => 'ep', 'params' => $arr]);

        self::assertEquals($expected, $request);
    }

    public function testGetGuzzleRequest(): void
    {
        $client = new RestClient(new NullLogger(), [], []);
        $requestGet = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1]]);
        $requestPost = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1], 'method' => 'POST']);
        $requestForm = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1], 'method' => 'FORM']);

        $get = self::callMethod($client, 'getGuzzleRequest', [$requestGet]);
        $post = self::callMethod($client, 'getGuzzleRequest', [$requestPost]);
        $form = self::callMethod($client, 'getGuzzleRequest', [$requestForm]);

        self::assertEquals('ep?a=1', $get->getUrl());

        self::assertEquals('ep', $post->getUrl());
        self::assertEquals('{"a":1}', $post->getBody());

        self::assertEquals('ep', $form->getUrl());
        self::assertEquals(['a' => 1], $form->getBody()->getFields());
    }

    public function testDownload(): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $mock = new Mock([
            new Response(200, [], Stream::factory($body)),
        ]);

        $history = new History();

        $restClient = new RestClient(new NullLogger(), [], []);
        $restClient->getClient()->setDefaultOption('headers', ['X-Test' => '1234']);
        $restClient->getClient()->getEmitter()->attach($mock);
        $restClient->getClient()->getEmitter()->attach($history);

        $request = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1]]);

        self::assertEquals(json_decode($body), $restClient->download($request));
        self::assertEquals('ep?a=1', $history->getLastRequest()->getUrl());
        self::assertEquals('GET', $history->getLastRequest()->getMethod());
        self::assertEquals(
            [1234],
            $history->getLastRequest()->getHeaders()['X-Test']
        );
    }

    public function testRequestHeaders(): void
    {
        $mock = new Mock([
            new Response(200, [], Stream::factory('{}')),
        ]);
        $history = new History();
        $restClient = new RestClient(new NullLogger(), [], []);
        $restClient->getClient()->setDefaultOption('headers', ['X-Test' => '1234']);
        $restClient->getClient()->getEmitter()->attach($mock);
        $restClient->getClient()->getEmitter()->attach($history);

        $request = new RestRequest([
            'endpoint' => 'ep',
            'params' => [],
            'method' => 'GET',
            'headers' => ['X-RTest' => 'requestHeader'],
        ]);
        $restClient->download($request);

        self::assertEquals(
            [
                'X-RTest' => ['requestHeader'],
                'X-Test' => ['1234'],
            ],
            $history->getLastRequest()->getHeaders()
        );
    }

    protected function runBackoff(RestClient $restClient, Response $errResponse): void
    {
        $body = '[
                {"field": "data"},
                {"field": "more"}
        ]';

        $mock = new Mock([
            $errResponse,
            new Response(200, [], Stream::factory($body)),
        ]);
        $restClient->getClient()->getEmitter()->attach($mock);

        $history = new History();
        $restClient->getClient()->getEmitter()->attach($history);

        $request = new RestRequest(['endpoint' => 'ep', 'params' => ['a' => 1]]);
        self::assertEquals(json_decode($body), $restClient->download($request));
        self::assertEquals(5000, $history->getLastRequest()->getConfig()['delay'], '', 1000);
    }

    /**
     * Cannot use dataProvider because that gets set up before all tests
     * and the delay causes issues
     */
    public function testStatusBackoff(): void
    {
        $sets = [
            'default' => [
                new RestClient(new NullLogger()),
                new Response(429, ['Retry-After' => 5]),
            ],
            'custom' => [
                new RestClient(
                    new NullLogger(),
                    [],
                    [
                        'http' => [
                            'retryHeader' => 'X-Rate-Limit-Reset',
                            'codes' => [403, 429],
                        ],
                        'maxRetries' => 8,
                    ]
                ),
                new Response(403, ['X-Rate-Limit-Reset' => 5]),
            ],
            'absolute' => [
                new RestClient(new NullLogger()),
                new Response(429, ['Retry-After' => time() + 5]),
            ],
        ];

        foreach ($sets as $set) {
            $this->runBackoff($set[0], $set[1]);
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
                self::assertRegExp('/retrying/ui', $record['message']);
                self::assertRegExp('/curl error 6\:/ui', $record['context']['message']);
            }

            self::assertRegExp('/curl error 6\:/ui', $e->getMessage());
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
            self::assertRegExp('/curl error 6\:/ui', $e->getMessage());
            self::assertTrue($e instanceof UserException);
        }
    }

    public function testErrorCodesIgnoreNoIgnore(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $client = new RestClient($logger, [], [], [], []);
        $mock = new Mock([
            new Response(404, [], Stream::factory('{"a": "b"}')),
        ]);
        $client->getClient()->getEmitter()->attach($mock);
        try {
            $client->download(new RestRequest(['endpoint' => 'ep']));
            self::fail('Request should fail');
        } catch (\Throwable $e) {
            self::assertStringContainsString('Not Found', $e->getMessage());
            self::assertStringContainsString('404', $e->getMessage());
        }
    }

    public function testErrorCodesIgnore(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $client = new RestClient($logger, [], [], [], [404]);
        $mock = new Mock([
            new Response(404, [], Stream::factory('{"a": "b"}')),
        ]);
        $client ->getClient()->getEmitter()->attach($mock);
        $response = $client->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['a' => 'b'], (array) $response);
    }

    public function testErrorCodesIgnoreServer(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $client = new RestClient($logger, [], [], [], [503]);
        $responses = [];
        for ($i = 0; $i < 15; $i++) {
            $responses[] = new Response(503, [], Stream::factory('{"a": "b"}'));
        }
        $mock = new Mock($responses);
        $client ->getClient()->getEmitter()->attach($mock);
        $response = $client->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['a' => 'b'], (array) $response);
    }

    public function testErrorCodesIgnoreInvalidResponse(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $client = new RestClient($logger, [], [], [], [200]);
        $mock = new Mock([
            new Response(200, [], Stream::factory('{"a": bcd"')),
        ]);
        $client ->getClient()->getEmitter()->attach($mock);
        $response = $client->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['errorData' => '{"a": bcd"'], (array) $response);
    }

    public function testErrorCodesIgnoreInvalidResponseAndCode(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $client = new RestClient($logger, [], [], [], [404]);
        $mock = new Mock([
            new Response(404, [], Stream::factory('{"a": bcd"')),
        ]);
        $client ->getClient()->getEmitter()->attach($mock);
        $response = $client->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['errorData' => '{"a": bcd"'], (array) $response);
    }

    public function testErrorCodesIgnoreEmptyResponse(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $client = new RestClient($logger, [], [], [], [404]);
        $mock = new Mock([
            new Response(404, [], null),
        ]);
        $client ->getClient()->getEmitter()->attach($mock);
        $response = $client->download(new RestRequest(['endpoint' => 'ep']));
        self::assertEquals(['errorData' => ''], (array) $response);
    }

    public function testMalformedJson(): void
    {
        $body = '[
                {"field": "d
        ]';

        $restClient = new RestClient(new NullLogger());

        $mock = new Mock([
            new Response(200, [], Stream::factory($body)),
        ]);
        $restClient->getClient()->getEmitter()->attach($mock);

        $request = new RestRequest(['endpoint' => 'ep']);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Invalid JSON response from API: JSON decode error:');
        try {
            $restClient->download($request);
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
}
