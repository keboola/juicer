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
        $this->requestFactory = new GuzzleRequestFactory();
    }

    public function testGet(): void
    {
        $request = $this->requestFactory->create(
            new RestRequest(
                ['endpoint' => 'ep', 'params' => ['a' => 1], 'headers' => ['X-Test' => 'test']]
            )
        );
        self::assertEquals('ep?a=1', (string) $request->getUri());
        self::assertEquals(
            ['X-Test' => ['test']],
            $request->getHeaders()
        );
    }

    public function testPost(): void
    {
        $request = $this->requestFactory->create(
            new RestRequest(
                ['endpoint' => 'ep', 'params' => ['a' => 1], 'method' => 'POST', 'headers' => ['X-Test' => 'test']]
            )
        );
        self::assertEquals('ep', (string) $request->getUri());
        self::assertEquals('{"a":1}', $request->getBody()->getContents());
        self::assertEquals(
            ['X-Test' => ['test'], 'Content-Type' => ['application/json']],
            $request->getHeaders()
        );
    }

    public function testForm(): void
    {
        $request = $this->requestFactory->create(
            new RestRequest(
                ['endpoint' => 'ep', 'params' => ['a' => 1], 'method' => 'FORM', 'headers' => ['X-Test' => 'test']]
            )
        );
        self::assertEquals('ep', (string) $request->getUri());
        self::assertEquals('a=1', $request->getBody()->getContents());
        self::assertEquals(
            ['X-Test' => ['test'], 'Content-Type' => ['application/x-www-form-urlencoded']],
            $request->getHeaders()
        );
    }
}
