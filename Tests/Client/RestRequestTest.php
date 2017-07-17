<?php

namespace Keboola\Juicer\Tests\Client;

use Keboola\Juicer\Client\RestRequest;
use PHPUnit\Framework\TestCase;

class RestRequestTest extends TestCase
{
    public function testCreate()
    {
        $request = new RestRequest([
            'endpoint' => 'ep',
            'params' => [
                'first' => 1,
                'second' => 'two'
            ]
        ]);

        self::assertEquals([], $request->getHeaders());
        self::assertEquals('ep', $request->getEndpoint());
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals(['first' => 1, 'second' => 'two'], $request->getParams());
        self::assertEquals('GET ep first=1&second=two', (string)$request);
    }

    public function testToString()
    {
        $request = new RestRequest([
            'endpoint' => 'ep',
            'headers' => [
                'foo' => 'bar'
            ],
            'params' => [
                'first' => 'foo',
                'second' => 'bar'
            ]
        ]);
        self::assertEquals('GET ep first=foo&second=bar', (string)$request);
    }

    public function testToStringPost()
    {
        $request = new RestRequest([
            'endpoint' => 'ep',
            'headers' => [
                'foo' => 'bar'
            ],
            'method' => 'POST',
            'params' => [
                'first' => 'foo',
                'second' => 'bar'
            ]
        ]);
        self::assertEquals("POST ep {\n    \"first\": \"foo\",\n    \"second\": \"bar\"\n}", (string)$request);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The "params" property must be an array.
     */
    public function testValidateConfig1()
    {
        new RestRequest(['endpoint' => 'ep', 'params' => 'string']);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The "endpoint" property must be specified in request as a string.
     */
    public function testValidateConfig2()
    {
        new RestRequest(['params' => ['string']]);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The "headers" property must be an array.
     */
    public function testValidateConfig3()
    {
        new RestRequest(['endpoint' => 'foo', 'headers' => 'string']);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage The "method" property must be on of "GET", "POST", "FORM".
     */
    public function testValidateConfig4()
    {
        new RestRequest(['endpoint' => 'foo', 'method' => 'string']);
    }
}
