<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Client;

use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Exception\UserException;
use PHPUnit\Framework\TestCase;

class RestRequestTest extends TestCase
{
    public function testCreate(): void
    {
        $request = new RestRequest([
            'endpoint' => 'ep',
            'params' => [
                'first' => 1,
                'second' => 'two',
            ],
        ]);

        self::assertEquals([], $request->getHeaders());
        self::assertEquals('ep', $request->getEndpoint());
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals(['first' => 1, 'second' => 'two'], $request->getParams());
        self::assertEquals('GET ep first=1&second=two', (string) $request);
    }

    public function testToString(): void
    {
        $request = new RestRequest([
            'endpoint' => 'ep',
            'headers' => [
                'foo' => 'bar',
            ],
            'params' => [
                'first' => 'foo',
                'second' => 'bar',
            ],
        ]);
        self::assertEquals('GET ep first=foo&second=bar', (string) $request);
    }

    public function testToStringPost(): void
    {
        $request = new RestRequest([
            'endpoint' => 'ep',
            'headers' => [
                'foo' => 'bar',
            ],
            'method' => 'POST',
            'params' => [
                'first' => 'foo',
                'second' => 'bar',
            ],
        ]);
        self::assertEquals("POST ep {\n    \"first\": \"foo\",\n    \"second\": \"bar\"\n}", (string) $request);
    }

    public function testValidateConfig1(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('The "params" property must be an array.');
        new RestRequest(['endpoint' => 'ep', 'params' => 'string']);
    }

    public function testValidateConfig2(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('The "endpoint" property must be specified in request as a string.');
        new RestRequest(['params' => ['string']]);
    }

    public function testValidateConfig3(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('The "headers" property must be an array.');
        new RestRequest(['endpoint' => 'foo', 'headers' => 'string']);
    }

    public function testValidateConfig4(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('The "method" property must be on of "GET", "POST", "FORM".');
        new RestRequest(['endpoint' => 'foo', 'method' => 'string']);
    }
}
