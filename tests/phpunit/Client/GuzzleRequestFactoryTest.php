<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Client;

use Keboola\Juicer\Client\GuzzleRequestFactory;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Tests\ExtractorTestCase;

class GuzzleRequestFactoryTest extends ExtractorTestCase
{
    private GuzzleRequestFactory $requestFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestFactory = new GuzzleRequestFactory(null);
    }

    public function testGet(): void
    {
        $request = $this->requestFactory->create(
            new RestRequest(
                ['endpoint' => 'ep', 'params' => ['a' => 1], 'headers' => ['X-Test' => 'test']],
            ),
        );
        self::assertEquals('ep?a=1', (string) $request->getUri());
        self::assertEquals(
            ['X-Test' => ['test']],
            $request->getHeaders(),
        );
    }

    public function testPost(): void
    {
        $request = $this->requestFactory->create(
            new RestRequest(
                ['endpoint' => 'ep', 'params' => ['a' => 1], 'method' => 'POST', 'headers' => ['X-Test' => 'test']],
            ),
        );
        self::assertEquals('ep', (string) $request->getUri());
        self::assertEquals('{"a":1}', $request->getBody()->getContents());
        self::assertEquals(
            ['X-Test' => ['test'], 'Content-Type' => ['application/json']],
            $request->getHeaders(),
        );
    }

    public function testForm(): void
    {
        $request = $this->requestFactory->create(
            new RestRequest(
                ['endpoint' => 'ep', 'params' => ['a' => 1], 'method' => 'FORM', 'headers' => ['X-Test' => 'test']],
            ),
        );
        self::assertEquals('ep', (string) $request->getUri());
        self::assertEquals('a=1', $request->getBody()->getContents());
        self::assertEquals(
            ['X-Test' => ['test'], 'Content-Type' => ['application/x-www-form-urlencoded']],
            $request->getHeaders(),
        );
    }

    public function testDefaultHeaderNotSet(): void
    {
        // Default host header is not set
        $this->requestFactory = new GuzzleRequestFactory(null);

        // No host header in request config
        $request = $this->requestFactory->create(new RestRequest([
            'endpoint' => 'http://example.com', // <<<<<<<<<
            'headers' => [
                'X-Test' => 'test',
            ],
        ]));

        // Used host header from URL
        self::assertEquals(
            [
                'X-Test' => ['test'],
                'Host' => ['example.com'], // <<<<<<<<<
            ],
            $request->getHeaders(),
        );
    }

    public function testDefaultHeaderUsed(): void
    {
        // Default host header is set
        $this->requestFactory = new GuzzleRequestFactory(
            'myhost.com', // <<<<<<<<<
        );

        // No host header in request config
        $request = $this->requestFactory->create(new RestRequest([
            'endpoint' => 'http://example.com',
            'headers' => [
                'X-Test' => 'test',
            ],
        ]));

        // Used default header
        self::assertEquals(
            [
                'X-Test' => ['test'],
                'Host' => ['myhost.com'], // <<<<<<<<<
            ],
            $request->getHeaders(),
        );
    }

    public function testDefaultHeaderNotUsed(): void
    {
        // Default host header is set
        $this->requestFactory = new GuzzleRequestFactory(
            'myhost.com',
        );

        // Header set in request
        $request = $this->requestFactory->create(new RestRequest([
            'endpoint' => 'http://example.com',
            'headers' => [
                'X-Test' => 'test',
                'Host' => 'host123.org', // <<<<<<<<<
            ],
        ]));

        // Used header from request
        self::assertEquals(
            [
                'X-Test' => ['test'],
                'Host' => ['host123.org'], // <<<<<<<<<
            ],
            $request->getHeaders(),
        );
    }
}
