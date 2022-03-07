<?php

declare(strict_types=1);

namespace Keboola\Juicer\Tests\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\Juicer\Tests\HistoryItem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

class HistoryItemTest extends TestCase
{
    public function testSuccessfulRequest(): void
    {
        $request = new Request('GET', 'example.com');
        $response = new Response();
        $options = ['foo' => 'bar'];
        $item = HistoryItem::fromArray([
            'request'  => $request,
            'response' => $response,
            'error'    => null,
            'options'  => $options,
        ]);

        self::assertSame($request, $item->getRequest());
        self::assertTrue($item->hasResponse());
        self::assertSame($response, $item->getResponse());
        self::assertFalse($item->hasError());
        self::assertSame($options, $item->getOptions());

        try {
            $item->getError();
            self::fail('Exception expected');
        } catch (UnexpectedValueException $e) {
            // ok
        }
    }

    public function testFailedRequest(): void
    {
        $request = new Request('GET', 'example.com');
        $error = new RuntimeException();
        $options = ['foo' => 'bar'];
        $item = HistoryItem::fromArray([
            'request'  => $request,
            'response' => null,
            'error'    => $error,
            'options'  => $options,
        ]);

        self::assertSame($request, $item->getRequest());
        self::assertFalse($item->hasResponse());
        self::assertTrue($item->hasError());
        self::assertSame($error, $item->getError());
        self::assertSame($options, $item->getOptions());

        try {
            $item->getResponse();
            self::fail('Exception expected');
        } catch (UnexpectedValueException $e) {
            // ok
        }
    }
}
